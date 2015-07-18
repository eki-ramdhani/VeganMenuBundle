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

    /**
     * @param string $permalink
     * @return array|bool
     */
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
     * @return $this
     */
    public function addMenu(Menu $menu)
    {
        if ($this->hasMenu($menu->getAnchor())) {
            throw new \InvalidArgumentException("Menu with anchor `{$menu->getAnchor()}` already exists!");
        }
        $this->collection[$menu->getAnchor()] = $menu;

        return $this;
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
     * @return $this
     */
    public function removeMenu($menuAnchor)
    {
        if ($this->hasMenu($menuAnchor)) {
            unset($this->collection[$menuAnchor]);
        }
        return $this;
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
     * @return $this
     */
    public function setMenuCollection(array $collection = array())
    {
        foreach ($collection as $menu) {
            if (!($menu instanceof Menu)) {
                throw new \InvalidArgumentException('VeganMenuBundle MenuCollection::setMenuCollection argument 1 \$collection must be array of Menu instances! Got: ' . gettype($menu));
            }
        }
        $this->collection = $collection;
        return $this;
    }
}
