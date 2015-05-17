<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Nette\Caching\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;


class DatabaseMenuBuilder
{
    /** @var Connection $connection Database connection (Could be Doctrine DBAL or PHP \PDO */
    protected $connection;

    /** @var \Doctrine\ORM\EntityManager $entityManager */
    protected $entityManager;

    /** @var string $locale Default locale */
    protected $locale;

    /** @var MenuCollection $menuCollection */
    protected $menuCollection;

    /** @var bool $loaded Was the MenuCollection already loaded? */
    protected $loaded = false;

    /** @var ContainerInterface $container */
    protected $container;

    /** @var VeganUrlGenerator $generator */
    protected $generator;

    /** @var null|\Nette\Caching\Cache $cache */
    protected $cache = null;

    /** @var bool $useCache */
    protected $useCache = false;


    /**
     * @param ContainerInterface $container
     * @param VeganUrlGenerator $generator
     * @param EntityManager $entityManager
     */
    public function __construct(ContainerInterface $container, VeganUrlGenerator $generator, EntityManager $entityManager = null)
    {
        $this->container = $container;
        $this->generator = $generator;
        $this->menuCollection = new MenuCollection();

        $this->entityManager = ($entityManager instanceof \Doctrine\ORM\EntityManagerInterface) ? $entityManager : $container->get('doctrine.orm.default_entity_manager');
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $request = $this->container->get('request');
        $request->setDefaultLocale($container->getParameter('locale'));
        $this->locale = $request->getLocale();
    }

    public function cache($useCache = true)
    {
        if (true === $useCache) {
            $this->cache = $this->container->get('nette.caching')->getCache();
        } else {
            $this->cache = null;
        }
        $this->useCache = (bool)$useCache;
    }

    public function cacheEnable()
    {
        $this->cache(true);
    }

    public function cacheDisable()
    {
        $this->cache(false);
    }

    public function hasCache()
    {
        return $this->cache !== null;
    }


    /**
     * @param $menuAnchor
     * @return Menu
     * @throws \Exception
     */
    public function getMenu($menuAnchor)
    {
        $this->isLoaded();

        if (!$this->menuCollection->hasMenu($menuAnchor)) {
            throw new \InvalidArgumentException("Menu with anchor `{$menuAnchor}` does not exists! Maybe you forget load this menu? Available menus: " . implode(', ', $this->menuCollection->getAllMenuAnchors()));
        }
        $menu = $this->menuCollection->getMenu($menuAnchor);
        if (false === $this->useCache) {
            // smažeme z cache toto menu, pro jistotu
            $this->cacheEnable();
            $menuKey = 'vegan.menu.'.$menu->getAnchor().'.'.$this->locale;
            $this->cache->clean(array(
                Cache::TAGS => array("menu/{$menu->getAnchor()}"),
            ));
            $this->cache->remove($menuKey);
            $this->cacheDisable();
        }
        return $menu;
    }

    /**
     * @param $menuAnchor
     * @param $itemAnchor
     *
     * @return MenuItem
     */
    public function getMenuItem($menuAnchor, $itemAnchor)
    {
        $menu = $this->getMenu($menuAnchor);
        $item = $menu->findMenuItem($itemAnchor);
        if (false === $item) {
            throw new \InvalidArgumentException("Menu `{$menuAnchor}` does not have item with anchor `{$itemAnchor}`!");
        }
        return $item;
    }


    public function setActiveItem(array $by = array())
    {
        // TODO: nastavit aktuální položku menu podle různých parametrů nebo kritérií
    }


    /**
     * @return MenuCollection
     * @throws \Exception
     */
    public function getMenuCollection()
    {
        $this->isLoaded();

        return $this->menuCollection;
    }


    /**
     * Metoda, která vygeneruje požadovaná menu (identifikace podle `anchor` což je unikátní kotva celého menu, třeba 'footer' nebo 'main' apod.)
     *
     * Tato metoda dokáže využívat cachování obsahu, takže není potřeba sahat pokaždé do databáze a pokaždé generovat obsah všech menu!
     * Každé menu má svůj identifikátor: vegan.menu.[anchor].[locale] takže je možné je z cache odstranit, nebo vygenerovat novou cache ...
     *
     * @param array $anchors Zůstane-li prázdné, generujeme všechna aktivní - nesmazaná menu. Chceme-li jen konkrétní kotvu, pak vložíme konkrétní hodnoty
     * @param array $rootNodes   Chceme-li asociovat pro menu anchor root položku (například chceme načíst uzel main.item-1)
     * @param array $menuOptions Každé menu může mít vlastní nastavení, $menuOptions['footer'] = array('položky nastavení');
     */
    public function generate(array $anchors = array(), array $rootNodes = array(), array $menuOptions = array())
    {
        /**
         * At first we will look to the Cache system and will try to fetch some cached items.
         */
        $cachedMenus = array();
        if (true === $this->useCache) {
            if (null === $this->cache) {
                $this->cache(true);
            }
            foreach ($anchors as $index => $anchor) {
                // například: vegan.menu.footer.cs_CZ
                $menuKey = 'vegan.menu.'.$anchor.'.'.$this->locale;
                if (array_key_exists($anchor, $rootNodes)) {
                    // chceme zobrazit nějaký uzel, takže musíme prohlédnout cache tohoto uzle
                    $menuKey .= '.' . $rootNodes[$anchor];
                }
                $menu = $this->cache->load($menuKey);
                if (null !== $menu) {
                    $cachedMenus[] = $menu;
                    unset($anchors[$index]);
                }
            }
        }

        $packOfMenuID = array();

        /** 1. vybereme všechny aktivní menu */

        $menus = $this->findMenus($anchors);

        $builders = array();

        foreach ($menus as $index => $row) {
            if (!array_key_exists($row['anchor'], $builders)) {
                $options = array_key_exists($row['anchor'], $menuOptions) ? $menuOptions[$row['anchor']] : array();
                $builders[$row['anchor']] = (new MenuBuilder($this->container, $this->generator, $this->useCache))->createMenu($row['anchor'], $options, $this->locale);
            }
            $packOfMenuID[] = $row['id'];
        }

        /** 2. načteme kompletní stromy menu v jediném SQL dotazu. Setřídíme nejdříve podle menu_id a poté left */

        $tree = $this->findItems($packOfMenuID);
        $defaultRoutes = array();

        foreach ($tree as $index => $row)
        {
            /** @var MenuBuilder $builder */
            $builder = $builders[$row['menu_anchor']];
            $defaultRoutes[$row['menu_anchor']] = $row['default_route'];

            $options = array(
                'name' => $row['name'],
                'slug' => $row['slug'],
                'parent' => $row['parent_anchor'],
                'permalink' => $row['permalink'],
                'route_name' => $row['route_name'],
                'locale' => $row['locale'],
            );

            if (is_null($options['parent']) || empty($options['parent'])) {
                unset($options['parent']);
            }

            if (is_null($options['permalink']) || empty($options['permalink'])) {
                unset($options['permalink']);
                $options['permalink_generate'] = true;
            }

            $builder->createItem($row['anchor'], $options);
        }

        foreach ($builders as $builder) {
            $menu = $builder->getMenu();
            $menu->setDefaultRouteName($defaultRoutes[$menu->getAnchor()]);
            $menu->getItems()->setLocale($this->locale);

            if (array_key_exists($menu->getAnchor(), $rootNodes))
            {
                $rootItem = $menu->findMenuItem($rootNodes[$menu->getAnchor()]);
                if (false === $rootItem) {
                    throw new \InvalidArgumentException("DatabaseMenuBuilder::generate invalid option \$rootNodes! Menu anchor `{$rootNodes[$menu->getAnchor()]}` does not exists for Menu `{$menu->getAnchor()}`!");
                }
                $menu->removeItems();
                $menu->addMenuItem($rootItem, 'root');
            }

            $this->menuCollection->addMenu($menu);
            if (true === $this->useCache) {
                $menuKey = 'vegan.menu.'.$menu->getAnchor().'.'.$this->locale;

                if (array_key_exists($menu->getAnchor(), $rootNodes)) {
                    $menuKey .= '.' . $rootNodes[$menu->getAnchor()];
                }
                if (!$this->hasCache()) {
                    $this->cacheEnable();
                }

                $this->cache->save($menuKey, $menu, array(
                    Cache::TAGS => array("menu/{$menu->getAnchor()}")
                ));
                /**
                 * Now we saved and tagged the Menu cache with tag 'menu/menu-anchor'
                 *
                 * If we want to clean a menu from cache (and all of sub cached items) we will call:
                 *
                 *      $cache->clean(
                 *          Cache::TAGS => array("menu/footer", "menu/main", "menu/left"),
                 *      );
                 *
                 * The Cache will be cleaned and next request will be again started from database (when we will see changes)
                 */
            }
        }

        foreach ($cachedMenus as $menuAnchor => $menu) {
            $this->menuCollection->addMenu($menu);
        }

        $this->loaded = true;
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

        $builder = $this->entityManager->createQueryBuilder();
        $builder
            ->select('menu.id')
            ->addSelect('menu.anchor')
            ->addSelect('translation.name')
            ->from('VeganMenuBundle:VeganMenu', 'menu')
            ->leftJoin('menu.translation', 'translation')
            ->where('menu.deletedAt is null')
            ->andWhere('menu.isActive = 1')
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $this->locale, \PDO::PARAM_STR)
        ;

        if (count($anchors) > 0) {
            $builder->andWhere($builder->expr()->in('menu.anchor', $anchors));
        }

        return $builder->getQuery()->getArrayResult();
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

        $builder = $this->entityManager->createQueryBuilder();

        $builder
            ->from('VeganMenuBundle:VeganMenuItem', 'item')
            ->leftJoin('item.translation', 'translation')
            ->leftJoin('item.parent', 'parent')
            ->leftJoin('item.menu', 'menu')
            ->leftJoin('menu.translation', 'menuTranslation')
            ->addSelect('translation.name')
            ->addSelect('translation.slug')
            ->addSelect('translation.permalink')
//            ->addSelect('item.treeLeft')
//            ->addSelect('item.treeRight')
//            ->addSelect('item.treeLevel')
            ->addSelect('menu.anchor AS menu_anchor')
            ->addSelect('parent.anchor AS parent_anchor')
            ->addSelect('item.anchor')
            ->addSelect('CASE WHEN (translation.route IS NOT NULL) THEN translation.route ELSE menuTranslation.defaultRoute END AS route_name')
            ->addSelect('menuTranslation.defaultRoute as default_route')
            ->addSelect('translation.locale')
            ->addSelect('menuTranslation.locale as menu_locale')
//            ->orderBy('item.menu', 'ASC')
//            ->addOrderBy('item.treeLeft', 'ASC')
        ;

        return $builder->getQuery()->getResult();
//
//        // TODO: zamyslet se, zda načítat položky, které neobsahují překlad a nebo informace o routě
//
//        // TODO: zahrnout do položek i tabulky *_extra pro načítání dalších funkcionalit (class, obrázky apod.)
//
//        $sql = <<<MYSQL
//              SELECT
//                trans.`name`,
//                trans.`slug`,
//                trans.`permalink`,
//                menu_item.`anchor` AS parent_anchor,
//                item.`anchor`,
//                menu.`anchor` AS menu_anchor,
//                router.`route_name`
//              FROM `vegan_menu_item` item
//              LEFT JOIN `vegan_menu` menu ON (menu.`id` = item.`menu_id`)
//              LEFT JOIN `vegan_menu_item` menu_item ON (item.`parent_id` = menu_item.`id`)
//              LEFT JOIN `vegan_menu_item_translation` trans ON (trans.`item_id` = item.`id`)
//              LEFT JOIN `vegan_router` router ON (trans.`route_id` = router.`route_id`)
//              WHERE
//                    item.`is_active` = 1
//                AND item.`deleted_at` IS NULL
//                AND item.`menu_id` IN (:inArray)
//              ORDER BY item.`menu_id` ASC, item.`tree_left` ASC
//MYSQL;
//        $stmt = $this->connection->prepare($sql);
//
//        $packOfMenuID = implode(',', $packOfMenuID);
//
//        $stmt->bindParam(':inArray', $packOfMenuID, \PDO::PARAM_STR);
//        $stmt->execute();
//
//        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDefaultMenuOptions()
    {
        return $this->container->get('vegan.menu.builder')->getDefaultMenuOptions();
    }


    /**
     * Check if collection of menus was loaded
     *
     * @throws \Exception
     */
    private function isLoaded()
    {
        if (true !== $this->loaded) {
            throw new \Exception("DatabaseMenuBuilder: No menu was loaded! At first you have to call method `generate` before asking about menu or item.");
        }
    }
}
