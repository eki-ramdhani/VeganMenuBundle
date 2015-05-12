<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 3.5.15 11:31
 */

namespace Vegan\Bundle\MenuBundle\Menu;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Třída pro dynamické generování menu z databáze
 *
 * TODO: zamyslet se, zda je potřeba využívat tree_right a tree_level? používáme pouze tree_left, menu_anchor, parent_anchor (takže parent_id a menu_id)
 */
class SimpleDatabaseMenuBuilder
{
    /** @var Connection $connection */
    protected $connection;

    /** @var string $locale Defaultní nebo aktuální locale */
    protected $locale;

    /** @var array $menu Seznam všech menu v asociativním poli */
    protected $menu;

    /** @var bool $loaded Kontrola, zda bylo menu vygenerováno */
    protected $loaded = false;


    protected $left = 0;
    protected $right = 0;
    protected $level = 0;


    /**
     * SimpleDatabaseMenuBuilder je třída, která dokáže načíst strom všech potřebných menu a následně s nimi dokáže pracovat bez dalších databázových dotazů
     *
     * @param Connection $connection
     * @param RequestStack $requestStack
     * @param $defaultLocale
     */
    public function __construct(Connection $connection, RequestStack $requestStack, $defaultLocale)
    {
        $this->connection = $connection;
        $request = $requestStack->getCurrentRequest();
        $request->setDefaultLocale($defaultLocale);
        $this->locale = $request->getLocale();
//        $this->rebuildTree();
    }



    /**
     * Metoda, která vygeneruje požadovaná menu (identifikace podle `anchor` což je unikátní kotva celého menu)
     *
     * @param array $anchors    Zůstane-li prázdné, generujeme všechna aktivní - nesmazaná menu. Chceme-li jen konkrétní kotvu, pak vložíme konkrétní hodnoty
     * @throws \Doctrine\DBAL\DBALException
     */
    public function generate(array $anchors = array())
    {
        $packOfMenuID = array();

        /** 1. vybereme všechny aktivní menu */
        try {
            $menus = $this->findMenus($anchors);
        } catch (\Exception $e) {
            $menus = array();
        }

        foreach ($menus as $index => $row) {
            $resultMenus[$row['anchor']] = $row;
            $resultMenus[$row['anchor']]['_children'] = array();
            $packOfMenuID[] = $row['id'];
        }

        /** 2. načteme kompletní stromy menu v jediném SQL dotazu. Setřídíme nejdříve podle menu_id a poté left */
        try {
            $tree = $this->findItems($packOfMenuID);
        } catch (\Exception $e) {
            $tree = array();
        }

        $resultMenus = array();

        foreach ($tree as $index => $row) {
            $resultMenus[$row['menu_anchor']]['_children'][$row['anchor']] = $row;
        }

        $this->menu = $resultMenus;
        $this->loaded = true;
    }




    /**
     * Metoda pro získání celého stromu menu (včetně všech zanořených potomků a potomků potomků)
     *
     * @param string $anchor Kotva daného menu (například 'footer', 'main' apod.)
     * @return array
     */
    public function getMenuTree($anchor)
    {
        $this->isLoaded();

        if (!array_key_exists($anchor, $this->menu)) {
            throw new \InvalidArgumentException("VeganMenuBuilder: `{$anchor}` is invalid anchor! Available: " . implode(', ', array_keys($this->menu)));
        }
        return $this->findRootChildren($anchor);
    }




    /**
     * Metoda pro získání surového menu bez struktury
     *
     * @param string|null $anchor
     * @return array
     */
    public function getRawMenu($anchor = null)
    {
        if (is_null($anchor)) {
            return $this->menu;
        }

        if (!array_key_exists($anchor, $this->menu)) {
            throw new \InvalidArgumentException("VeganMenuBuilder: `{$anchor}` is invalid anchor. Available anchors: " . implode(', ', array_keys($this->menu)));
        }

        return $this->menu[$anchor];
    }




    /**
     * Metoda pro získání konkrétní položky menu
     *
     * @param string $menuAnchor
     * @param string $itemAnchor
     * @return array
     */
    public function getItem($menuAnchor, $itemAnchor)
    {
        $this->isLoaded();

        $menu = $this->getRawMenu($menuAnchor);
        foreach ($menu['_children'] as $anchor => $data) {
            if ($itemAnchor === $anchor) {
                return $data;
            }
        }
        throw new \InvalidArgumentException("VeganMenuBuilder: item anchor `{$itemAnchor}` for menu anchor `{$menuAnchor}` does not exists!");
    }




    /**
     * Metoda pro získání všech potomků požadovaného uzlu
     * UPOZORNĚNÍ: metoda $this->findChildrens postupně odstraňuje prvky z pole $this->menu, kvůli zrychlení rekurze. Takže je třeba zálohovat menu a následně zálohu obnovit
     *
     * @param string $menuAnchor    Kotva menu
     * @param string $itemAnchor    Kotva požadované položky menu
     * @return array
     */
    public function getChildren($menuAnchor, $itemAnchor)
    {
        $menu = $this->menu[$menuAnchor];
        $children = $this->findChildrens($menuAnchor, $itemAnchor);
        $this->menu[$menuAnchor] = $menu;
        return $children;
    }


    /**
     * Metoda pro získání pole nadřazených položek (de facto navigace)
     *
     * @param string $menuAnchor Pro jaké menu?
     * @param string $itemAnchor Pro jakou položku?
     * @param bool $includeSelf  Chceme zahrnout do stromu aktuální položku?
     * @return array
     */
    public function getParents($menuAnchor, $itemAnchor, $includeSelf = false)
    {
        $item = $this->getItem($menuAnchor, $itemAnchor);

        if (true === $includeSelf) {
            $result[$item['anchor']] = $item;
        } else {
            $result = array();
        }

        $protection = 0;
        $parentAnchor = $item['parent_anchor'];

        do {
            if (is_null($parentAnchor)) {
                break;
            }

            $item = $this->getItem($menuAnchor, $parentAnchor);
            $result[$item['anchor']] = $item;
            $parentAnchor = $item['parent_anchor'];

            $protection++;
            if ($protection > 1000) {
                break;
            }
        } while (true);

        return array_reverse($result);
    }




    /**
     * Metoda pro vyhledání všech root potomků daného menu
     *
     * @param $menuAnchor
     * @return array
     * @internal
     */
    private function findRootChildren($menuAnchor)
    {
        $result = array();
        $menu = $this->menu[$menuAnchor];
        foreach ($this->menu[$menuAnchor]['_children'] AS $anchor => $item)
        {
            if ($item['menu_anchor'] === $menuAnchor && is_null($item['parent_anchor']))
            {
                $result[$item['anchor']] = $item;
                $result[$item['anchor']]['_children'] = $this->findChildrens($menuAnchor, $item['anchor']);
            }
        }
        $this->menu[$menuAnchor] = $menu;
        return $result;
    }


    /**
     * Rekurzivní metoda pro získání celého stromu potomků od daného uzlu (hledáme podle kotvy [anchor])
     *
     * @param $menuAnchor
     * @param $itemAnchor
     * @return array
     *
     * @internal Je třeba dávat pozor, protože tato rekurzivní metoda odstraňuje nalezené položky z $this->menu[$menuAnchor] kvůli zrychlení rekurze
     */
    private function findChildrens($menuAnchor, $itemAnchor)
    {
        $result = array();
        foreach ($this->menu[$menuAnchor]['_children'] as $anchor => $item)
        {
            if ($item['parent_anchor'] === $itemAnchor)
            {
                $result[$item['anchor']] = $item;
                $result[$item['anchor']]['_children'] = $this->findChildrens($menuAnchor, $item['anchor']);
                unset($this->menu[$menuAnchor]['_children'][$anchor]);  // odstraníme položku kvůli zrychlení rekurze
            }
        }
        return $result;
    }


    /**
     * Kontrola, zda bylo menu načteno
     *
     * @throws \Exception
     */
    private function isLoaded()
    {
        if (true !== $this->loaded) {
            throw new \Exception("VeganMenuBuilder: No menu was loaded! At first you have to call method `generate` before asking about menu or item.");
        }
    }


    /**
     * Metoda pro získání všech menu podle 'anchor' (kotvy)
     *
     * @internal
     *
     * @param array $anchors
     * @param bool $loadAll
     *
     * @return array
     */
    private function findMenus(array $anchors = array(), $loadAll = false)
    {
        if (false === $loadAll && 0 === count($anchors)) {
            return array();
        }

        if (0 === count($anchors)) {
            $whereAnchors = null;
        } else {
            foreach ($anchors as $index => $anchor) {
                $anchors[$index] = '\'' . $anchor . '\'';
            }
            $whereAnchors = " AND menu.`anchor` IN (" . implode(',', $anchors) . ") ";
        }

        $sql = <<<SQL
            SELECT
              menu.`id`,
              menu.`anchor`,
              trans.`name`
            FROM `vegan_menu` menu
            LEFT JOIN `vegan_menu_i18l` trans ON (trans.`menu_id` = menu.`id` AND trans.`locale` = :locale)
            WHERE
                menu.`is_active` = 1
            AND (menu.`deleted_at` IS NULL OR menu.`deleted_at` = '')
            {$whereAnchors}
SQL;
        $sql = preg_replace('/\s+/', ' ', $sql);    // odebereme zbytečné mezery ...

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':locale', $this->locale, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    /**
     * Metoda pro získání celého stromu podle balíčku ID všech menu
     *
     * @param array $packOfMenuID
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     *
     * @internal
     */
    private function findItems(array $packOfMenuID = array())
    {
        if (0 === count($packOfMenuID)) {
            return array();
        }

        // TODO: zamyslet se, zda načítat položky, které neobsahují překlad a nebo informace o routě

        // TODO: zahrnout do položek i tabulky *_extra pro načítání dalších funkcionalit (class, obrázky apod.)

        $sql = <<<SQL
              SELECT
                trans.`name`,
                trans.`permalink`,
                menu_item.`anchor` AS parent_anchor,
                item.`anchor`,
                menu.`anchor` AS menu_anchor,
                router.`route_name`
              FROM `vegan_menu_item` item
              LEFT JOIN `vegan_menu` menu ON (menu.`id` = item.`menu_id`)
              LEFT JOIN `vegan_menu_item` menu_item ON (item.`parent_id` = menu_item.`id`)
              LEFT JOIN `vegan_menu_item_i18l` trans ON (trans.`item_id` = item.`id`)
              LEFT JOIN `vegan_router` router ON (trans.`route_id` = router.`route_id`)
              WHERE
                    item.`is_active` = 1
                AND item.`deleted_at` IS NULL
                AND item.`menu_id` IN (:inArray)
              ORDER BY item.`menu_id` ASC, item.`tree_left` ASC
SQL;
        $stmt = $this->connection->prepare($sql);

        $packOfMenuID = implode(',', $packOfMenuID);

        $stmt->bindParam(':inArray', $packOfMenuID, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }




    public function rebuildTree()
    {
        $stmt = $this->connection->prepare('SELECT m.`root_item_id` FROM `vegan_menu` m');
        $stmt->execute();
        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($menus as $menu) {
            $this->left = 1;
            $this->level = 1;
            $this->rebuildWorker($menu['root_item_id'], $this->left, $this->level);
        }

    }



    private function rebuildWorker($parentID, $left, $level)
    {
        $this->right = $left + 1;

        $sql = 'SELECT `id` FROM `vegan_menu_item` WHERE `parent_id` = :parentID ORDER BY `tree_left` ASC, `created_at` ASC, `id` ASC';

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':parentID', $parentID, \PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($result as $child) {
            list($this->left, $this->right) = $this->rebuildWorker($child['id'], $this->right, ($level + 1));
        }

        $sql = "UPDATE `vegan_menu_item` SET `tree_left` = {$left}, `tree_right` = {$this->right}, `tree_level` = {$level} WHERE `id` = {$parentID}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();

        return array($left, $this->right + 1);
    }


//    private function insertItem($parentID, $level, $right)
//    {
//        $sql = 'UPDATE `vegan_menu_item` SET `tree_left` = `tree_left` + 1 AND `tree_right` = `tree_right` + 2 WHERE tree_left >= ';
//    }

}
