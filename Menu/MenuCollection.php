<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

class MenuCollection
{
    /**
     * @var Menu[]
     */
    protected $collection;

    public function __construct()
    {
        $this->collection = array();
    }

    public function findActiveMenuItemByPermalink($permalink)
    {
        $result = false;
        /** @var Menu $menu */
        foreach ($this->collection as $menu) {
            $item = $menu->findMenuItemByPermalink($permalink);
            if (false !== $item) {
                $result = array();
                $item->setActive(true);
                $menu->setMenuActiveItem($item);
                $result['item'] = $item;
                $result['menu'] = $menu;
                break;
            }
        }
        return $result;
    }

    /**
     * @param Menu $menu
     */
    public function addMenu(Menu $menu)
    {
        if ($this->hasMenu($menu->getAnchor())) {
            throw new \InvalidArgumentException("Menu with anchor `{$menu->getAnchor()}` already exists!");
        }
        $this->collection[$menu->getAnchor()] = $menu;

    }

    /**
     * @param string $menuAnchor
     * @return Menu
     */
    public function getMenu($menuAnchor)
    {
        if (!$this->hasMenu($menuAnchor)) {
            return false;
        }
        return $this->collection[$menuAnchor];
    }

    /**
     * @param string $menuAnchor
     * @return bool
     */
    public function hasMenu($menuAnchor)
    {
        return array_key_exists($menuAnchor, $this->collection);
    }

    /**
     * @param string $menuAnchor
     */
    public function removeMenu($menuAnchor)
    {
        if ($this->hasMenu($menuAnchor)) {
            unset($this->collection[$menuAnchor]);
        }
    }

    /**
     * @return array
     */
    public function getAllMenuAnchors()
    {
        return array_keys($this->collection);
    }

    /**
     * @param Menu[] $collection
     */
    public function setMenuCollection(array $collection = array())
    {
        $this->collection = $collection;
    }
}
