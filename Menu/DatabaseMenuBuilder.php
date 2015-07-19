<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Nette\Caching\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Vegan\MenuBundle\Entity\VeganMenuItem;
use Vegan\MenuBundle\Entity\VeganMenuItemAttribute;


class DatabaseMenuBuilder
{
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
     */
    public function __construct(ContainerInterface $container, VeganUrlGenerator $generator)
    {
        $this->container = $container;
        $this->generator = $generator;
        $this->menuCollection = new MenuCollection();

        $this->entityManager = $container->get('vegan.menu.entity_manager');
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
     * Get result of loaded MenuCollection
     *
     * @return MenuCollection
     * @throws \Exception
     */
    public function getMenuCollection()
    {
        $this->isLoaded();

        return $this->menuCollection;
    }


    /**
     * The method, which generates the desired menus (identification by `anchor` a unique anchor of the entire menu, like 'footer' or 'main' etc.).
     *
     * This method can use content caching, so there is no need to generate database queries every Request!
     *
     *      Every cached menu has own identifier: vegan.menu.[anchor].[locale] so you can clean from cache Menus you want simply:
     *          $cache->remove('vagen.menu.footer.en_US');
     *
     * @param array $anchors      Which menu `anchor` we want load?
     * @param array $rootNodes    Do you want associate Root node for some menu?
     * @param array $menuOptions  Do you want pass to the menu default options? array('menu-anchor' => $menuOptions)
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Nette\InvalidArgumentException
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

                $menuKey = 'vegan.menu.'.$anchor.'.'.$this->locale;
                if (array_key_exists($anchor, $rootNodes)) {

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

        /** Load all Menus */

        $menus = $this->findMenus($anchors);

        $builders = array();

        foreach ($menus as $index => $row) {
            if (!array_key_exists($row['anchor'], $builders)) {
                $options = array_key_exists($row['anchor'], $menuOptions) ? $menuOptions[$row['anchor']] : array();

                $builders[$row['anchor']] = (new MenuBuilder($this->container, $this->generator, $this->useCache))->createMenu($row['anchor'], $row['default_route'], $this->locale, $options);
            }
            $packOfMenuID[] = $row['id'];
        }

        /** Load Menu tree */

        $tree = $this->findItems($packOfMenuID);
        $attributes = $this->findAttributes($packOfMenuID);

        $defaultRoutes = array();

        foreach ($tree as $index => $row)
        {
            /** @var MenuBuilder $builder */
            $builder = $builders[$row['menu_anchor']];
            $defaultRoutes[$row['menu_anchor']] = $row['default_route'];

            $packOfMenuItemID[] = $row['id'];

            $options = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'parent' => $row['parent_anchor'],
                'permalink' => $row['permalink'],
                'route_name' => $row['route_name'],
                'locale' => $row['locale'],
                'attributes' => (array_key_exists($row['menu_anchor'], $attributes) && array_key_exists($row['anchor'], $attributes[$row['menu_anchor']])) ? $attributes[$row['menu_anchor']][$row['anchor']] : new ArrayCollection(),
            );

            if (null === $options['parent'] || empty($options['parent'])) {
                unset($options['parent']);
            }

            if (null === $options['permalink'] || empty($options['permalink'])) {
                unset($options['permalink']);
                $options['permalink_generate'] = true;
            }

            $builder->createItem($row['anchor'], $options);
        }

        foreach ($builders as $builder) {
            $menu = $builder->getMenu();
            if (is_array($defaultRoutes) && array_key_exists($menu->getAnchor(), $defaultRoutes)) {
                $menu->setDefaultRouteName($defaultRoutes[$menu->getAnchor()]);
            } else {
                throw new \LogicException("DatabaseMenuBuilder::generate Menu with anchor `{$menu->getAnchor()}` does not have any MenuItems!");
                // TODO: refactor that exception if Menu does not have any MenuItem (how to setup default route_name?)
            }
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
     * Get Menus from database
     *
     * @internal
     *
     * @param array $anchors
     * @param bool $loadAll
     *
     * @return array
     */
    protected function findMenus(array $anchors = array(), $loadAll = false)
    {
        if (false === $loadAll && 0 === count($anchors)) {
            return array();
        }

        $builder = $this->entityManager->createQueryBuilder();
        $builder
            ->from('VeganMenuBundle:VeganMenu', 'menu')
            ->select('menu.id')
            ->addSelect('menu.anchor')
            ->addSelect('translation.name')
            ->addSelect('translation.defaultRoute AS default_route')
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
     * Get menus tree from database (by 1 query we will find Menu tree for every menu)
     *
     * @param array $packOfMenuID
     * @return VeganMenuItem[]
     * @throws \Doctrine\DBAL\DBALException
     *
     * @internal
     */
    protected function findItems(array $packOfMenuID = array())
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
            ->where($builder->expr()->in('item.menu', $packOfMenuID))
            ->addSelect('translation.name')
            ->addSelect('translation.slug')
            ->addSelect('translation.permalink')
            ->addSelect('item.id')
            ->addSelect('item.treeLeft')
            ->addSelect('item.treeRight')
            ->addSelect('item.treeLevel')
            ->addSelect('menu.anchor AS menu_anchor')
            ->addSelect('parent.anchor AS parent_anchor')
            ->addSelect('item.anchor')
            ->addSelect('CASE WHEN (translation.route IS NOT NULL) THEN translation.route ELSE menuTranslation.defaultRoute END AS route_name')
            ->addSelect('menuTranslation.defaultRoute as default_route')
            ->addSelect('translation.locale')
            ->addSelect('menuTranslation.locale AS menu_locale')
            ->andWhere('item.isActive = 1')
            ->andWhere('item.deletedAt IS NULL')
            ->andWhere('translation.locale = :locale')
            ->andWhere('menuTranslation.locale = :locale2')
            ->setParameter('locale', $this->locale)
            ->setParameter('locale2', $this->locale)
            ->orderBy('item.menu', 'ASC')
            ->addOrderBy('item.treeLeft', 'ASC')
        ;

        return $builder->getQuery()->getResult();
    }



    /**
     * @param array $packOfMenuID
     * @return array
     */
    protected function findAttributes(array $packOfMenuID = array())
    {
        $builder = $this->entityManager->createQueryBuilder();

        $builder
            ->from('VeganMenuBundle:VeganMenuItemAttribute', 'attribute')
            ->join('attribute.menuItem', 'item')
            ->join('item.translation', 'translation')
            ->join('item.menu', 'menu')
            ->addSelect('attribute')
            ->addSelect('item')
            ->addSelect('translation')
            ->addSelect('menu')
            ->andWhere($builder->expr()->in('menu.id', $packOfMenuID))
            ->andWhere('attribute.locale = :locale')
            ->andWhere('item.isActive = 1')
            ->andWhere('item.deletedAt IS NULL')
            ->andWhere('translation.locale = :locale2')
            ->setParameter('locale', $this->locale)
            ->setParameter('locale2', $this->locale)
        ;

        $array = $builder->getQuery()->getResult();
        $temp = array();
        $result = array();

        foreach ($array as $row)
        {
            /** @var VeganMenuItemAttribute $row */
            $items = $row->getMenuItems()->getIterator();
            foreach ($items as $item)
            {
                /** @var VeganMenuItem $item */
                $temp[$item->getMenu()->getAnchor()][$item->getAnchor()][] = $row;
            }
            $row->clearMenuItems();
        }

        foreach ($temp as $menuAnchor => $menuItems)
        {
            if (0 === count($menuItems)) {
                continue;
            }
            foreach ($menuItems as $itemAnchor => $attributes)
            {
                if (0 === count($attributes)) {
                    continue;
                }
                $attribute = new ArrayCollection();
                foreach ($attributes as $attr)
                {
                    /** @var VeganMenuItemAttribute $attr */
                    $attribute->set($attr->getAttribute(), $attr->getValue());
                }
                $result[$menuAnchor][$itemAnchor] = $attribute;
            }
        }
        return $result;
    }



    /**
     * Get default Menu options [global variables that you can override]
     *
     * @return array
     */
    public function getDefaultMenuOptions()
    {
        return $this->container->get('vegan.menu.builder')->getDefaultMenuOptions();
    }


    /**
     * Check if collection of menus was loaded
     *
     * @throws \LogicException
     */
    protected function isLoaded()
    {
        if (true !== $this->loaded) {
            throw new \LogicException('DatabaseMenuBuilder: No menu was loaded! At first you have to call method `generate` before asking about menu or item.');
        }
    }
}
