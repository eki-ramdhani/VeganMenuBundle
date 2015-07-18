<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

class Menu
{
    /** @var string $anchor Unique menu anchor (you can handle that menu by `anchor`) */
    protected $anchor;

    /** @var MenuItem $root Root menu item has no parent. Every MenuItem added to this menu without setted parent will have parent root */
    protected $items;

    /** @var MenuItem|null $activeItem */
    protected $activeItem;

    /** @var string $defaultRouteName Name of the default route which will be used if the route_name for MenuItem was not setted */
    protected $defaultRouteName;


    /**
     * @param string $menuAnchor
     * @param string $defaultRouteName
     */
    public function __construct($menuAnchor, $defaultRouteName)
    {
        $this->anchor = $menuAnchor;
        $this->defaultRouteName = $defaultRouteName;
        $this->items = new MenuItem('root');
    }

    /**
     * @return string
     */
    public function getDefaultRouteName()
    {
        return $this->defaultRouteName;
    }

    /**
     * @param $routeName
     * @return $this
     */
    public function setDefaultRouteName($routeName)
    {
        $this->defaultRouteName = $routeName;
        return $this;
    }

    /**
     * @return MenuItem
     */
    public function getItems()
    {
        return $this->items;
    }

    public function removeItems()
    {
        $this->items->removeChildren();
    }

    public function getActiveItem()
    {
        return $this->activeItem;
    }

    //-----

    /**
     * Get menu `anchor`
     *
     * @return string
     */
    public function getAnchor()
    {
        return $this->anchor;
    }

    //----- Get, set, has MenuItem:

    /**
     * Add MenuItem inside Menu
     *
     * @param MenuItem $item
     * @param null $parentAnchor
     */
    public function addMenuItem(MenuItem $item, $parentAnchor = null)
    {
        if ($item->getAnchor() === 'root') {
            throw new \InvalidArgumentException("Cannot add item `root`. Root is generated automatic by __construct()");
        }

        if ($parentAnchor instanceof MenuItem) {
            $parentAnchor = $parentAnchor->getAnchor();
        }

        if (null === $parentAnchor || 'root' === $parentAnchor) {
            $this->items->addChild($item);
            $item->setParent($this->items);
        } else {
            $parentItem = $this->findMenuItem($parentAnchor);
            if (false === $parentItem) {
                throw new \InvalidArgumentException("Menu `{$this->anchor}` does not have any item with anchor `{$parentAnchor}`!");
            }
            $parentItem->addChild($item);
            $item->setParent($parentItem);
        }
    }

    /**
     * Get MenuItem from menu tree by `anchor`. Alias for `findMenuItem`
     *
     * @param $itemAnchor
     * @return bool|MenuItem
     */
    public function getMenuItem($itemAnchor)
    {
        return $this->findMenuItem($itemAnchor);
    }

    //----- Searching for MenuItem inside tree:

    /**
     * Recursive method to find MenuItem (by `anchor`) inside whole tree
     *
     * @param $itemAnchor
     * @param MenuItem $item
     * @return bool|MenuItem
     */
    public function findMenuItem($itemAnchor, MenuItem $item = null)
    {
        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $result = false;

        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->items->getChildren();
        } else {
            $children = $item->getChildren();
        }
        foreach ($children as $child) {
            if ($child->getAnchor() === $itemAnchor) {
                return $child;
            } else {
                $result = $this->findMenuItem($itemAnchor, $child);
                if ($result instanceof MenuItem) {
                    return $result;
                }
            }
        }
        return $result;
    }


    /**
     * Find MenuItem[] inside Menu tree by $options parameter:
     *
     *      $options = array(
     *          'slug' => 'some-slug',
     *          'active' => true,
     *          'locale' => 'en_US',
     *          ...
     *      )
     *
     * @param array $options
     * @param bool  $mustFulfillAllOptions  TRUE = MenuItem must fulfill every option, FALSE = MenuItem must fulfill at least one option
     * @param MenuItem $item Used by recursion, but you can pass MenuItem and it will find inside it children
     *
     * @return MenuItem[]
     */
    public function findMenuItems(array $options, $mustFulfillAllOptions = true, MenuItem $item = null)
    {
        if (0 === count($options)) {
            throw new \InvalidArgumentException("Array \$options cannot be empty!");
        }

        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->items->getChildren();
        } else {
            $children = $item->getChildren();
        }
        $result = array();

        foreach ($children as $child)
        {
            $optionsResults = array();
            foreach ($options as $key => $value)
            {
                if (!is_string($key) || empty($key)) {
                    throw new \InvalidArgumentException("Invalid argument \$options. Array of options must be associative array(key => value), for example: array('slug' => 'searching')!");
                }
                $key = ucfirst(strtolower($key));
                $callable = array($child, 'get' . $key);
                if (!is_callable($callable)) {
                    throw new \InvalidArgumentException("Invalid argument \$options for Menu::findMenuItems. Callable MenuItem::`get{$key}` does not exists! ");
                }
                $optionsResults[$key] = (call_user_func($callable) === $value);
            }

            if (true === $mustFulfillAllOptions) {
                if (!in_array(false, $optionsResults) && count($optionsResults) > 0) {
                    $result[] = $child;
                }
            } else {
                if (in_array(true, $optionsResults) && count($optionsResults) > 0) {
                    $result[] = $child;
                }
            }

            $childResults = $this->findMenuItems($options, $mustFulfillAllOptions, $child);
            foreach ($childResults as $childResult) {
                $result[] = $childResult;
            }
        }

        return $result;
    }


    /**
     * Find MenuItem[] inside Menu tree by $key and $value ($key can be any MenuItem variable like `slug`, `name`, `permalink`, `active` ...)
     *
     * @param string $key
     * @param string|integer $value
     *
     * @return MenuItem[]
     */
    public function findMenuItemsBy($key, $value)
    {
        return $this->findMenuItems(array($key => $value));
    }



    /**
     * Method to find MenuItem (by `name`) inside whole Menu tree (will return first finded item by name)
     *
     * @param $itemName
     * @return bool|MenuItem
     */
    public function findMenuItemByName($itemName)
    {
        $result = $this->findMenuItemsBy('name', $itemName);
        if (count($result) === 0) {
            return false;
        }
        return array_shift($result);
    }


    /**
     * Method to find MenuItem (by `slug`) inside whole Menu tree
     *
     * @param $itemSlug
     * @return bool|MenuItem
     */
    public function findMenuItemBySlug($itemSlug)
    {
        $result = $this->findMenuItemsBy('slug', $itemSlug);
        if (count($result) === 0) {
            return false;
        }
        return array_shift($result);
    }


    /**
     * Recursive method to find MenuItem (by `permalink`) inside whole tree
     *
     * @param $itemPermalink
     * @return bool|MenuItem
     */
    public function findMenuItemByPermalink($itemPermalink)
    {
        $result = $this->findMenuItemsBy('permalink', $itemPermalink);
        if (count($result) === 0) {
            return false;
        }
        return array_shift($result);
    }

    //----- Positions:

    /**
     * Find item position inside parent MenuItem children
     *
     * @param $itemAnchor
     * @return int
     */
    public function getItemPosition($itemAnchor)
    {
        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $position = 1;
        if ($itemAnchor === 'root') {
            return $position;
        }
        $item = $this->findMenuItem($itemAnchor);
        if (false === $item) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not exist! It's impossible to get item position.");
        }
        if (!$item->hasParent()) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not have any parent! It's impossible to get item position.");
        }
        $parent = $item->getParent();
        $children = $parent->getChildren();
        foreach ($children as $child) {
            if ($child->getAnchor() === $itemAnchor) {
                break;
            }
            $position++;
        }
        return $position;
    }

    /**
     * Move item to first position inside parent MenuItem collection
     *
     * @param string $itemAnchor
     */
    public function moveItemToFirstPosition($itemAnchor)
    {
        $this->moveItemToPosition($itemAnchor, 1);
    }

    /**
     * Move item to the last position inside parent MenuItem collection
     *
     * @param string $itemAnchor
     */
    public function moveItemToLastPosition($itemAnchor)
    {
        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $item = $this->findMenuItem($itemAnchor);
        if (false === $item) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not exist! It's impossible to change position.");
        }
        if (!$item->hasParent()) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not have any parent! It's impossible to change position.");
        }

        $parent = $item->getParent();
        $children = $parent->getChildren();

        $partBefore = array();
        $actualPosition = 0;
        foreach ($children as $child) {
            $actualPosition++;
            if ($child->getAnchor() !== $itemAnchor) {
                $partBefore[$actualPosition] = $child;
            }
        }
        $parent->removeChildren();
        foreach ($partBefore as $child) {
            $parent->addChild($child);
        }
        $parent->addChild($item);
    }

    /**
     * Move item to another position inside parent MenuItem collection
     *
     * @param string $itemAnchor
     * @param integer $position
     */
    public function moveItemToPosition($itemAnchor, $position)
    {
        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $item = $this->findMenuItem($itemAnchor);
        if (false === $item) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not exist! It's impossible to change position.");
        }

        if (!$item->hasParent()) {
            throw new \InvalidArgumentException("Menu item `{$itemAnchor}` does not have any parent! It's impossible to change position.");
        }

        $parent = $item->getParent();
        $children = $parent->getChildren();

        $partBefore = array();
        $partActual = array();
        $partAfter = array();
        $change = false;

        $actualPosition = 0;
        foreach ($children as $child) {
            $actualPosition++;
            if ($actualPosition === $position) {
                if ($child->getAnchor() === $itemAnchor) {
                    $partActual[$actualPosition] = $child;
                } else {
                    $partAfter[$actualPosition] = $child;
                }
                $change = true;
            } else {
                if ($child->getAnchor() === $itemAnchor) {
                    $partActual[$actualPosition] = $child;
                } else if (true === $change) {
                    $partAfter[$actualPosition] = $child;
                } else {
                    $partBefore[$actualPosition] = $child;
                }
            }
        }

        $parent->removeChildren();

        foreach ($partBefore as $child) {
            $parent->addChild($child);
        }
        foreach ($partActual as $child) {
            $parent->addChild($child);
        }
        foreach ($partAfter as $child) {
            $parent->addChild($child);
        }
    }

    /**
     * @param MenuItem $item
     * @param $parentAnchor
     */
    public function moveToParent(MenuItem $item, $parentAnchor)
    {
        $parent = $this->findMenuItem($parentAnchor);
        if (false === $parent) {
            throw new \InvalidArgumentException("Parent anchor `{$parentAnchor}` was not found in method moveToParent for item anchor `{$item->getAnchor()}`");
        }

        if ($item->hasParent()) {
            $itemParent = $item->getParent();
            $itemParent->removeChild($item->getAnchor());
        }

        $parent->addChild($item);
        $item->setParent($parent);
    }

    /**
     * Alias for `moveToParent`
     * @param MenuItem $item
     * @param $parentAnchor
     */
    public function changeParent(MenuItem $item, $parentAnchor)
    {
        $this->moveToParent($item, $parentAnchor);
    }

    //----- Activating or Deactivating MenuItem

    /**
     * Activate or Deactivate MenuItem by `permalink` (optional you can activate|deactivate all parents tree)
     *
     * @param string $permalink   Permalink (whole menu tree slugs, e.g.: base-menu/sub-menu/sub-sub-menu)
     * @param bool $setActive     True for activate, false for deactivate
     * @param bool $affectParents Do you want set same state to all parent tree?
     *
     * @return bool
     */
    public function setActiveItemByPermalink($permalink, $setActive = true, $affectParents = false)
    {
        if ($permalink instanceof MenuItem) {
            $permalink = $permalink->getPermalink();
        }

        $result = false;
        $item = $this->findMenuItemByPermalink($permalink);
        if (false !== $item) {
            $item->setActive($setActive);
            $this->setMenuActiveItem($item);
            if (true === $affectParents) {
                $parents = $this->getParents($item->getAnchor());
                foreach ($parents as $parent) {
                    $parent->setActive($setActive);
                }
            }
            $result = true;
        }
        return $result;
    }


    /**
     * Activate or Deactivate MenuItem by `slug` (optional you can activate|deactivate all parents tree by parameter $affectParents)
     *
     * @param string $slug           Slug (unique url-identifier)
     * @param bool   $setActive      True for activate, false for deactivate
     * @param bool   $affectParents  Do you want set same state to all parent tree?
     *
     * @return bool
     */
    public function setActiveItemBySlug($slug, $setActive = true, $affectParents = false)
    {
        if ($slug instanceof MenuItem) {
            $slug = $slug->getSlug();
        }

        $result = false;
        $item = $this->findMenuItemBySlug($slug);
        if (false !== $item) {
            $item->setActive($setActive);
            $this->setMenuActiveItem($item);
            if (true === $affectParents) {
                $parents = $this->getParents($item->getAnchor());
                foreach ($parents as $parent) {
                    $parent->setActive($setActive);
                }
            }
            $result = true;
        }
        return $result;
    }


    /**
     * Activate or Deactivate MenuItem by `name` (optional you can activate|deactivate all parents tree by parameter $affectParents)
     *
     * @param string $name           Name (translation) of MenuItem
     * @param bool   $setActive      True for activate, false for deactivate
     * @param bool   $affectParents  Do you want set same state to all parent tree?
     *
     * @return bool
     */
    public function setActiveItemByName($name, $setActive = true, $affectParents = false)
    {
        if ($name instanceof MenuItem) {
            $name = $name->getName();
        }

        $result = false;
        $item = $this->findMenuItemByName($name);
        if (false !== $item) {
            $item->setActive($setActive);
            $this->setMenuActiveItem($item);
            if (true === $affectParents) {
                $parents = $this->getParents($item->getAnchor());
                foreach ($parents as $parent) {
                    $parent->setActive($setActive);
                }
            }
            $result = true;
        }
        return $result;
    }

    //----- Manipulation with parents

    /**
     * Get whole tree of parents by item `anchor`
     *
     * @param string|MenuItem $itemAnchor
     * @return MenuItem[]|array
     */
    public function getParents($itemAnchor)
    {

        $result = array();
        if ($itemAnchor instanceof MenuItem) {
            $item = $itemAnchor;
        } else {
            $item = $this->findMenuItem($itemAnchor);
        }
        if (false === $item) {
            return array();
        }

        $result = $item->getParents();

//        do {
//            if (false === $item || !$item->hasParent()) {
//                break;
//            }
//            $parent = $item->getParent();
//            if ($parent->getAnchor() === 'root') {
//                break;
//            } else {
//                $item = $this->findMenuItem($parent->getAnchor());
//                $result[$parent->getAnchor()] = $item;
//            }
//        } while ($item->hasParent());
        return array_reverse($result);
    }


    /**
     * Get array of all parents for breadcrumbs navigation (you have optional option to include self item inside breadcrumb)
     *
     * @param string  $itemAnchor   Anchor of MenuItem wich you want to get parents
     * @param bool    $includeSelf  Want to include self (MenuItem) to breadcrumb tree?
     *
     * @return MenuItem[]
     */
    public function getBreadcrumbArray($itemAnchor, $includeSelf = false)
    {
        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $parents = $this->getParents($itemAnchor);

        if (true === $includeSelf) {
            $parents[] = $this->findMenuItem($itemAnchor);
        }
        return $parents;
    }

    //------ Generating `permalink` (pack of parent slugs. e.g.: /menu/sub-menu/sub-sub-menu/

    /**
     * Generating permalink for MenuItem. It will passing through parent tree and folds every slugs together with slash
     *
     * @param string|MenuItem $itemAnchor
     * @param bool $startWithSlash Do you want permalink starting with slash /?
     * @param bool $endWithSlash Do you want permalink ending with slash /?
     * @param null $prepend Prepend before permalink
     * @param null $append  Append after permalink
     */
    public function generatePermalink($itemAnchor, $startWithSlash = false, $endWithSlash = false, $prepend = null, $append = null)
    {
        $slugs = array();
        $permalink = null;

        if ($itemAnchor instanceof MenuItem) {
            $itemAnchor = $itemAnchor->getAnchor();
        }

        $items = $this->getBreadcrumbArray($itemAnchor, true);

        /** @var MenuItem $item */
        foreach ($items as $item)
        {
            if (false === $item) {
                throw new \InvalidArgumentException("Method generatePermalink failed because some parent MenuItem was not found!");
            }
            $slug = $item->getSlug();
            if (is_null($slug)) {
                throw new \InvalidArgumentException("Slug of parent item anchor `{$item->getAnchor()}` is null and it's not possible to generate permalink for item anchor `{$itemAnchor}`");
            }
            $slugs[] = $slug;
        }

        if (true === $startWithSlash) {
            $permalink .= '/';
        }
        $permalink .= implode('/', $slugs);
        if (true === $endWithSlash) {
            $permalink .= '/';
        }

        $permalink = $prepend . $permalink . $append;

        $item = $this->findMenuItem($itemAnchor);
        $item->setPermalink($permalink);
    }

    /**
     * If you want auto-generate permalinks for whole Menu tree
     *
     * @param bool $startWithSlash
     * @param bool $endWithSlash
     * @param MenuItem $item
     */
    public function generateMenuPermalinks($startWithSlash = false, $endWithSlash = false, MenuItem $item = null)
    {
        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->items->getChildren();
        } else {
            $children = $item->getChildren();
        }
        foreach ($children as $child) {
            $this->generatePermalink($child->getAnchor(), $startWithSlash, $startWithSlash);
            if ($child->hasChildren()) {
                $nextChildren = $child->getChildren();
                foreach ($nextChildren as $nextChild) {
                    $this->generatePermalink($nextChild->getAnchor(), $startWithSlash, $endWithSlash);
                    $this->generateMenuPermalinks($startWithSlash, $endWithSlash, $nextChild);
                }
            }
        }
    }

    //------ Manipulating with active MenuItem:

    /**
     * Has this menu some active MenuItem?
     *
     * @return boolean
     */
    public function hasMenuActiveItem()
    {
        return $this->activeItem !== null;
    }

    /**
     * Set this Menu active MenuItem
     *
     * @param MenuItem $item
     */
    public function setMenuActiveItem(MenuItem $item)
    {
        $this->activeItem = $item;
    }

    /**
     * Get active MenuItem
     *
     * @return null|MenuItem
     */
    public function getMenuActiveItem()
    {
        return $this->activeItem;
    }


    public function generateSpecial($setSameSpecialForAll = true, $rewriteOriginal = false, MenuItemSpecial $special = null, MenuItem $item = null)
    {
        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->items->getChildren();
        } else {
            $children = $item->getChildren();
        }

        if (is_null($special) || $setSameSpecialForAll === false) {
            $special = new MenuItemSpecial();
        }

        foreach ($children as $child)
        {
            if (true === $rewriteOriginal) {
                $child->setSpecial($special);
            } else {
                if (!$child->hasSpecial()) {
                    $child->setSpecial($special);
                }
            }
            $this->generateSpecial($setSameSpecialForAll, $rewriteOriginal, $special, $child);
        }
    }

    public function setSpecialForAll(MenuItemSpecial $special, MenuItem $item = null)
    {
        if (is_null($item) || $item->getAnchor() === 'root') {
            $children = $this->items->getChildren();
        } else {
            $children = $item->getChildren();
        }

        foreach ($children as $child)
        {
            $child->setSpecial($special);
            $this->setSpecialForAll($special, $child);
        }
    }

}
