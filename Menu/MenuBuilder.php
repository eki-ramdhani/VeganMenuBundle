<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\CompiledRoute;
use Vegan\MenuBundle\Component\ObjectManipulator;
use Vegan\MenuBundle\Component\SlugGenerator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Nette\Caching\Cache;

/**
 * MenuBuilder or how to build own Menu
 *
 * Example how to use MenuBuilder:
 * -----------------------------------------
$builder = $this->container->get('vegan.menu.builder');
// [or you can use]:
$builder = new MenuBuilder($this->container, ((new VeganUrlGenerator())->setContainer($this->container)));

$menu = $builder->createMenu('footer', '_default_route_name')  // we used `anchor` to be able to grab menu with any translated MenuItem and `default_route_name` which will be used if MenuItem does not have any own route name
        ->createItem('some-item-1', array(
            'name' => 'Some item 1',          // translated name of MenuItem (will be displayed in Frontend)
            'route_name' => '_route_name_1',  // route name
            'slug' => 'some-item-1',          // `slug` is unique URL key, that we can use in address, like: http://example.com/my-test-slug
            'permalink' => 'some-item-1',     // `permalink` is unique URL key including parents menu tree, like: http://example.com/my-parent/my-second-parent/my-test-slug
        ))
        ->createItem('some-item-2', array(
            'name' => 'Some item 2',
            'route_name' => '_route_name_2',
            'slug_generate' => true,          // now slug will be auto-generated to value: 'some-item-2' from option `name`
            'parent' => 'some-item-1',
            'permalink_generate'    => true,  // now the permalink will be auto generated, result will be: some-item-1/some-item-2
            'permalink_slash_start' => true,  // permalink will starts with slash: /some-item-1/some-item-2  !this option is default FALSE!
            'permalink_slash_end'   => true,  // permalink will ends with slash:   /some-item-1/some-item-2/ !this option is default FALSE!
        ))
        ->createItem('some-item-3', array(
            'name' => 'Some item 3',
            'route_name' => '_route_name_3',
            'slug' => 'some-item-3',
            'permalink_generate' => true,     // auto-generated permalink will be: some-item-1/some-item-2/some-item-3
        ))
        ->getMenu();
 */
class MenuBuilder
{
    /** @var Menu $menu Our menu we are building */
    private $menu;

    /** @var array $menuOptions Merged default Menu options with user Menu options (if you want default Menu options, call $builder->getDefaultMenuOptions(); */
    private $menuOptions;

    /** @var string $locale Menu locale (e.g. 'en_US') Is required for caching the Menu tree (you don't want to cache only 1 menu translation right? :-) */
    private $locale;

    /** @var array $menuItemsWithoutParent Item where we will try find `parent` in method `getMenu()` */
    private $menuItemsWithoutParent = array();

    /** @var array $menuItemsGenerateSlug Items where we will generate `slug` */
    private $menuItemsGenerateSlug = array();

    /** @var array $menuItemsGeneratePermalink Items where we will generate `permalink` */
    private $menuItemsGeneratePermalink = array();

    /** @var ContainerInterface $container */
    protected $container;

    /** @var \Nette\Caching\Cache null $cache */
    protected $cache = null;

    /** @var bool $useCache Do you want to use Nette\Caching component? */
    protected $useCache = false;

    /** @var bool $cached Was the menu already cached? */
    protected $cached = false;

    /** @var bool $clearCache Helper that signal clearing the cache */
    protected $clearCache = false;

    /** @var VeganUrlGenerator $generator Generator for URIs */
    protected $generator;

    public function __construct(ContainerInterface $container, VeganUrlGenerator $generator, $useCaching = false)
    {
        $this->container = $container;
        $this->generator = $generator;
        $this->menu = null;
        $this->menuOptions = $this->getDefaultMenuOptions();
        if (true === $useCaching) {
            $this->loadCache();
            $this->useCache = true;
        }
    }

    /**
     * Create new menu catchable by `anchor` (for any locale)
     *
     * @param string $menuAnchor  Menu `anchor` to grab menu for any locale
     * @param string $defaultRouteName Default route name will be used for each items without option route_name
     * @param string $locale      Which locale to use?
     * @param array $options You can overwrite Menu options $this->getDefaultMenuOptions() by this array
     *
     * @return $this
     */
    public function createMenu($menuAnchor, $defaultRouteName, $locale, array $options = array())
    {
        if (null !== $this->menu) {
            throw new \InvalidArgumentException("Method MenuBuilder::createMenu is possible to call only once!");
        }

        $this->locale = $locale;

        if (true === $this->useCache) {
            $menu = $this->cache->load('vegan.menu.'.$menuAnchor.'.'.$this->locale);
            if (null !== $menu) {
                // now the menu is loaded from cache
                $this->menu = $menu;
                $this->cached = true;
                return $this;
            }
        }
        $menu = new Menu($menuAnchor, $defaultRouteName);
        $defaultOptions = $this->getDefaultMenuOptions();

        if (is_array($options) && count($options) > 0)
        {
            foreach ($options as $key => $option)
            {
                $optionType = gettype($option);
                $defaultOption = $this->getOption($key);
                $defaultOptionType = gettype($defaultOption);

                if ($optionType !== $defaultOptionType)
                {
                    throw new \InvalidArgumentException("Invalid data type for option ['{$key}']. Must be type of " . $defaultOptionType);
                }
                $subOptions = $this->getOption($key);
                if ('array' === $optionType)
                {
                    foreach ($option as $subKey => $newOption)
                    {
                        $newOptionType = gettype($newOption);
                        $defaultSubOption = $this->getOption($key, $subKey);
                        $defaultSubOptionType = gettype($defaultSubOption);

                        if ($newOptionType !== $defaultSubOptionType)
                        {
                            throw new \InvalidArgumentException("Invalid data type for option [{$key}][{$subKey}]. Must be " . $defaultSubOptionType);
                        }
                        $subOptions[$subKey] = $newOption;
                    }
                    $defaultOptions[$key] = $subOptions;
                } else {
                    $defaultOptions[$key] = $options;
                }
            }
        }
        $this->menu = $menu;
        $this->menuOptions = $defaultOptions;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultMenuOptions()
    {
        // TODO: zahrnout všechna nastavení do kódu!
        return array(
            'slug' => array(
                'auto_generate' => false,
                'delimiter' => '-',         // if you will set auto_generate => true, then the delimiter will be used for join words (For example `hello how are you` with delimiter '-' will generate slug: `hello-how-are-you`
                'remove_words' => array(),  // do you want to remove some words from `name` before creating `slug`?
                'generate_from' => array('name'),  // can be any callables in object MenuItem::get*(), for example id, name, active, locale ..
                'rewrite_original' => false,// if used auto_generate, do you want rewrite original slug which was set manualy?
            ),
            'permalink' => array(
                'auto_generate' => false,   // do you want auto generate `permalink` for whole menu tree?
                'slash_start' => false,     // start permalinks with slash?
                'slash_end' => false,       // end permalinks with slash?
                'rewrite_original' => false,// if used auto_generate, do you want rewrite original permalink which was set manualy?
            ),
            'uri' => array(
                'auto_generate' => true,    // do you want auto generate MenuItem URI? e.g. /my-route/my-route-slug
                'path_type' => UrlGenerator::ABSOLUTE_PATH, // Path-type must implement UrlGenerator constant
            ),
            'try_find_parents' => true, // do you want to try find parents in method getMenu() ?
        );
    }


    /**
     * Vytvoření nové položky menu pomocí kotvy [itemAnchor] a parametrů [options]
     *
     * @param string $itemAnchor
     * @param array $options
     *
     * @return $this
     */
    public function createItem($itemAnchor, array $options = array())
    {
        if (true === $this->cached) {
            return $this;
        }
        $item = new MenuItem($itemAnchor);
        $save = true;
        $parentAnchor = 'root';

        if (is_array($options) && count($options) > 0) {

            /** NAME */
            if (array_key_exists('name', $options)) {
                $item->setName($options['name']);
                unset($options['name']);
            }

            /** ID */
            if (array_key_exists('id', $options)) {
                $item->setId($options['id']);
                unset($options['id']);
            }

            /** ROUTE_NAME */
            if (array_key_exists('route_name', $options)) {
                $item->setRouteName($options['route_name']);
                unset($options['route_name']);
            }

            /** SLUG */
            if (array_key_exists('slug', $options)) {
                $item->setSlug($options['slug']);
                unset($options['slug']);
            }

            $generateSlug = $this->getOption('slug', 'auto_generate');

            if ($generateSlug && $item->hasSlug()) {
                $generateSlug = $this->getOption('slug', 'rewrite_original');
            }

            if (array_key_exists('slug_generate', $options)) {
                $generateSlug = (bool)$options['slug_generate'];
            }

            if (true === $generateSlug)
            {
                $delimiter = array_key_exists('slug_delimiter', $options) ? (string)$options['slug_delimiter'] : $this->getOption('slug', 'delimiter');
                $removeWords = array_key_exists('slug_remove_words', $options) ? (array)$options['slug_remove_words'] : $this->getOption('slug', 'remove_words');
                $from = array_key_exists('slug_generate_from', $options) ? $options['slug_generate_from'] : $this->getOption('slug', 'generate_from');

                $this->menuItemsGenerateSlug[] = array(
                    'item' => $item,
                    'delimiter' => $delimiter,
                    'remove_words' => $removeWords,
                    'generate_from' => $from,
                );
            }

            if (array_key_exists('slug_generate', $options)) {
                unset($options['slug_generate']);
                if (array_key_exists('slug_delimiter', $options)) {
                    unset($options['slug_delimiter']);
                }
                if (array_key_exists('slug_remove_words', $options)) {
                    unset($options['slug_remove_words']);
                }
                if (array_key_exists('slug_generate_from', $options)) {
                    unset($options['slug_generate_from']);
                }
            }

            if (array_key_exists('slug_generate_from', $options) || array_key_exists('slug_delimiter', $options) || array_key_exists('slug_remove_words', $options)) {
                throw new \InvalidArgumentException("MenuItem with anchor `{$item->getAnchor()}` has option `slug_generate_from`, `slug_delimiter` or `slug_remove_words`. This options require option `slug`!");
            }

            /** PERMALINK */
            if (array_key_exists('permalink', $options)) {
                $item->setPermalink($options['permalink']);
                unset($options['permalink']);
            }

            $generatePermalink = $this->getOption('permalink', 'auto_generate');

            if (array_key_exists('permalink_generate', $options)) {
                $generatePermalink = (bool)$options['permalink_generate'];
            }

            if (false === $this->getOption('permalink', 'rewrite_original') && $item->hasPermalink()) {
                $generatePermalink = false;
            }

            if ($generatePermalink) {
                $slashStart = array_key_exists('permalink_slash_start', $options) ? $options['permalink_slash_start'] : $this->getOption('permalink', 'slash_start');
                $slashEnd = array_key_exists('permalink_slash_end', $options) ? $options['permalink_slash_end'] : $this->getOption('permalink', 'slash_end');
                $this->menuItemsGeneratePermalink[] = array(
                    'item' => $item,
                    'start' => (bool)$slashStart,
                    'end' => (bool)$slashEnd,
                );
            }

            if (isset($options['permalink_generate'])) {
                unset($options['permalink_generate']);
                if (isset($options['permalink_slash_start'])) {
                    unset($options['permalink_slash_start']);
                }
                if (isset($options['permalink_slash_end'])) {
                    unset($options['permalink_slash_end']);
                }
            }

            if (array_key_exists('permalink_slash_start', $options) || array_key_exists('permalink_slash_end', $options)) {
                throw new \InvalidArgumentException("Options `permalink_slash_start` and `permalink_slash_end` required option `permalink_generate` => true|false");
            }

            /** SPECIAL */
            if (array_key_exists('special', $options)) {
                $itemSpecial = $item->getSpecial();
                if (is_null($itemSpecial)) {
                    $item->createSpecial();
                    $itemSpecial = $item->getSpecial();
                }
                $special = $options['special'];
                if (is_array($special)) {
                    $itemSpecial->setArray($special);
                } else if ($special instanceof MenuItemSpecial) {
                    $item->setSpecial($special);
                } else {
                    throw new \InvalidArgumentException("Invalid \$options parameter: `special` must be associative array or instance of MenuItemSpecial!");
                }
                unset($options['special']);
            }

            /** LOCALE */
            if (array_key_exists('locale', $options)) {
                $item->setLocale($options['locale']);
                unset($options['locale']);
            }

            /** PARENT */
            if (array_key_exists('parent', $options)) {
                if ($options['parent'] === 'root') {
                    throw new \InvalidArgumentException("MenuBuilder invalid option `parent` - parent root is created automatically if you don't set parent option. Cannot set root manualy.");
                }

                if ($options['parent'] instanceof MenuItem) {
                    $options['parent'] = $options['parent']->getAnchor();
                }

                $parent = $this->menu->findMenuItem($options['parent']);

                if (false === $parent) {
                    // parent was not found, we will try to find him in method getMenu()
                    $this->addMenuItemWithoutParent($item, $options['parent']);
                    $save = false;
                } else {
                    $item->setParent($parent);
                    $parentAnchor = $parent->getAnchor();
                }
                unset($options['parent']);
            }

            /** ACTIVE */
            if (array_key_exists('active', $options)) {
                $item->setActive((bool)$options['active']);
                unset($options['active']);
            }

            if (count($options) > 0) {
                $keys = array();
                foreach ($options as $key => $value) {
                    ob_start();
                    var_dump($value);
                    $keys[] = "'" . $key . "' => " . ob_get_contents();
                    ob_end_clean();
                }
                throw new \InvalidArgumentException("Unresolved option(s) for MenuItem `{$item->getAnchor()}`:\n " . implode("\n", $keys));
            }
        }

        if (true === $save) {
            $this->menu->addMenuItem($item, $parentAnchor);
        }

        return $this;
    }


    /**
     * Získání výsledného menu
     *
     * @return Menu
     */
    public function getMenu()
    {
        if (true === $this->cached) {
            return $this->menu;
        }

        if (count($this->getMenuItemsWithoutParent()) > 0 && true === $this->getOption('try_find_parents')) {
            $this->tryFindParents();
        }

        if (count($this->menuItemsGenerateSlug) > 0) {
            $this->tryGenerateMenuSlugs();
        }

        if (count($this->menuItemsGeneratePermalink) > 0) {
            $this->tryGenerateMenuPermalinks();
        }

        if (true === $this->getOption('uri','auto_generate')) {
            $pathType = $this->getOption('uri','path_type');
            $pathTypes = array(UrlGenerator::ABSOLUTE_PATH, UrlGenerator::ABSOLUTE_URL, UrlGenerator::RELATIVE_PATH, UrlGenerator::NETWORK_PATH);
            if (!in_array($pathType, $pathTypes)) {
                throw new \InvalidArgumentException("MenuBuilder invalid option ['uri']['path_type']. Required constant from Symfony UrlGenerator: " . implode(', ', $pathTypes));
            }
            $this->generateMenuItemsURI($pathType);
        }

        if (true === $this->useCache)
        {
            $this->cache->save('vegan.menu.'.$this->menu->getAnchor().'.'.$this->locale, $this->menu, array(
                Cache::TAGS => array("menu/{$this->menu->getAnchor()}"),
            ));

            // TODO: vyřešit, jak dlouho se obsah bude cachovat

            $this->cached = true;
        } else {
            if ($this->hasCache()) {
                /** Need to clean cache */
                $this->cache->remove('vegan.menu.'.$this->menu->getAnchor().'.'.$this->locale);
                $this->cache->clean(array(
                    Cache::TAGS => array("menu/{$this->menu->getAnchor()}"),
                ));
            }
        }

        return $this->menu;
    }


    /**
     * Method that can generate URI (for example /app_dev.php/some/route/example)for whole menu tree by information inside Route
     *
     * @param bool $pathType
     * @param bool $rewrite Do you want rewrite every MenuItem $uri?
     * @param MenuItem $item Used by recursion
     */
    public function generateMenuItemsURI($pathType = UrlGenerator::ABSOLUTE_PATH, $rewrite = false, MenuItem $item = null)
    {
        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->menu->getItems()->getChildren();
        } else {
            $children = $item->getChildren();
        }

        foreach ($children as $child)
        {
            if (true === $rewrite || !$child->hasUri()) {
                $routeName = ($child->hasRouteName()) ? $child->getRouteName() : $this->menu->getDefaultRouteName();
                $route = $this->generator->getRoute($routeName);
                if (null === $route) {
                    throw new \InvalidArgumentException("Route name `{$routeName}` for MenuItem `{$child->getAnchor()}` does not exist!");
                }
                $options = array();

                /** HACK how we can access to the private (or protected) variables inside some Object like Route::$compiled [there is information about route needed parameters] */
                $routeObjectAsArray = ObjectManipulator::objectToArray($route);
                /** @var CompiledRoute $compiled */
                $compiled = $routeObjectAsArray['compiled'];

                if (!is_null($compiled)) {
                    $variables = $compiled->getVariables();

                    foreach ($variables as $index => $key)
                    {
                        $callable = array($child, 'get'.ucfirst(strtolower($key)));
                        if (is_callable($callable)) {
                            $options[$key] = call_user_func($callable);
                        }
                    }
                }

                $child->setUri($this->generator->generate($routeName, $options, $pathType));
            }
            if ($child->hasChildren()) {
                $this->generateMenuItemsURI($pathType, $rewrite, $child);
            }
        }
    }


    /**
     * Manually set Menu
     *
     * @param Menu $menu
     * @return $this
     */
    public function setMenu(Menu $menu)
    {
        $this->menu = $menu;

        return $this;
    }


    /**
     * Generate `permalink` for whole menu tree
     */
    public function tryGenerateMenuPermalinks()
    {
        foreach ($this->menuItemsGeneratePermalink as $index => $array) {
            /** @var MenuItem $item */
            $item = $array['item'];
            $startSlash = $array['start'];
            $endSlash = $array['end'];
            $this->menu->generatePermalink($item->getAnchor(), $startSlash, $endSlash);
//            unset($this->menuItemsGeneratePermalink[$index]);
        }
//        dump($this->menuItemsGeneratePermalink);
    }


    /**
     * We will try generate `slug` for every MenuItem in array
     */
    public function tryGenerateMenuSlugs()
    {
        foreach ($this->menuItemsGenerateSlug as $index => $array) {
            /** @var MenuItem $item */
            $item = $array['item'];
            $delimiter = $array['delimiter'];
            $remove = $array['remove_words'];
            $from = $array['generate_from'];
            if (is_array($from)) {
                $slugParts = array();
                foreach ($from as $fromPart) {
                    if (!is_string($fromPart)) {
                        throw new \InvalidArgumentException("MenuItem `{$item->getAnchor()}` failed to auto-generate slug. Invalid option `slug_generate_from` (or global menu option `slug`.`generate_from`). Only string or simple array is allowed!");
                    }
                    $callable = array($item, 'get'.ucfirst(strtolower($fromPart)));
                    if (!is_callable($callable)) {
                        throw new \InvalidArgumentException("Anchor `{$item->getAnchor()}` failed to auto-generate slug. No callable getter for variable `{$fromPart}` found in MenuItem!");
                    }
                    $string = call_user_func($callable);
                    $slugParts[] = SlugGenerator::generate($string, $remove, $delimiter);
                }
                $slug = implode($delimiter, $slugParts);
            } else {
                $callable = array($item, 'get'.ucfirst(strtolower($from)));
                if (!is_callable($callable)) {
                    throw new \InvalidArgumentException("MenuItem with anchor `{$item->getAnchor()}` has option `slug_generate_from` with value '{$from}'. No callable getter in MenuItem was found!");
                }
                $string = call_user_func($callable);
                $slug = SlugGenerator::generate($string, $remove, $delimiter);
            }
            $slug = trim($slug, $delimiter);
            $item->setSlug($slug);
        }
    }


    /**
     * @return array
     */
    private function getMenuItemsWithoutParent()
    {
        return $this->menuItemsWithoutParent;
    }


    /**
     * We will try to find parents for all MenuItem[]
     */
    private function tryFindParents()
    {
        $items = $this->getMenuItemsWithoutParent();
        foreach ($items as $index => $array)
        {
            /** @var MenuItem $item */
            $item = $array['item'];
            $parentAnchor = $array['parent'];
            $parent = $this->menu->findMenuItem($parentAnchor);

            if ($parent instanceof MenuItem) {
                /** parent MenuItem was found */
                $parent->addChild($item);
                $item->setParent($parent);
                unset($this->menuItemsWithoutParent[$index]);
            } else {
                /** parent MenuItem was not found, so we have to try find inside MenuItems without parents */
                $itemsWithout = $this->getMenuItemsWithoutParent();
                foreach ($itemsWithout as $indexWithout => $arrayWithout) {
                    /** @var MenuItem $itemWithout */
                    $itemWithout = $arrayWithout['item'];
                    if ($itemWithout->getAnchor() === $parentAnchor) {
                        $itemWithout->addChild($item);
                        $item->setParent($itemWithout);
                        unset($this->menuItemsWithoutParent[$index]);
                    }
                }
            }
        }

        $items = $this->getMenuItemsWithoutParent();
        if (count($items) > 0) {
            $keys = array_map(function($item) {
                if (array_key_exists('item', $item) && $item['item'] instanceof MenuItem) {
                    /** @var MenuItem $item */
                    $item = $item['item'];
                    return $item->getAnchor();
                } else {
                    throw new \InvalidArgumentException("Some MenuItem is not instance of MenuItem. Json_encoded \$item: " . json_encode($item));
                }
            }, $items);
            throw new \InvalidArgumentException("Cannot find parent for MenuItem anchor(s): " . implode(', ', $keys));
        }
    }


    /**
     * We didn't find parent for some MenuItem, so we will try to find it later (by method `tryFindParents()`
     *
     * @param MenuItem $item
     * @param $parentAnchor
     */
    private function addMenuItemWithoutParent(MenuItem $item, $parentAnchor)
    {
        $this->menuItemsWithoutParent[] = array(
            'item' => $item,
            'parent' => $parentAnchor,
        );
    }


    /**
     * @return VeganUrlGenerator
     */
    public function getGenerator()
    {
        return $this->generator;
    }


    /**
     * @param VeganUrlGenerator $generator
     */
    public function setGenerator(VeganUrlGenerator $generator)
    {
        $this->generator = $generator;
    }


    /**
     * @return array
     */
    public function getMenuOptions()
    {
        return $this->menuOptions;
    }


    /**
     * @param $key
     * @param null $secondaryKey
     *
     * @return mixed
     */
    private function getOption($key, $secondaryKey = null)
    {
        if (!$this->hasOption($key, $secondaryKey)) {
            throw new \InvalidArgumentException("MenuBuilder doesn't have option with key: ['{$key}']" . (null !== $secondaryKey ? "['{$secondaryKey}']" : null));
        }
        if (null === $secondaryKey) {
            return $this->menuOptions[$key];
        }
        return $this->menuOptions[$key][$secondaryKey];
    }


    /**
     * @param $key
     * @param null $secondaryKey
     *
     * @return bool
     */
    private function hasOption($key, $secondaryKey = null)
    {
        if (null === $secondaryKey) {
            return array_key_exists($key, $this->menuOptions);
        }
        return array_key_exists($key, $this->menuOptions) && array_key_exists($secondaryKey, $this->menuOptions[$key]);
    }


    /**
     * @return \Nette\Caching\Cache|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return bool
     */
    public function hasCache()
    {
        return $this->cache !== null;
    }

    /**
     * @throws \Exception
     */
    public function loadCache()
    {
        if (!$this->container->has('nette.caching')) {
            throw new \Exception("Component `nette.caching` is not available in container! Please install nette/caching by: composer require nette/caching");
        }
        $cache = $this->container->get('nette.caching')->getCache();
        $this->cache = $cache;
    }


    /**
     * Enable the menu caching
     */
    public function cacheEnable()
    {
        $this->cache(true);
    }


    /**
     * Disable menu caching
     */
    public function cacheDisable()
    {
        $this->cache(false);
        $this->clearCache = true;
    }


    /**
     * @param bool $useCache
     * @internal
     * @throws \Exception If Vegan\Component\NetteCaching is not available
     */
    private function cache($useCache = true)
    {
        $this->useCache = (bool)$useCache;
        if (!$this->hasCache()) {
            $this->loadCache();
        }
    }

}
