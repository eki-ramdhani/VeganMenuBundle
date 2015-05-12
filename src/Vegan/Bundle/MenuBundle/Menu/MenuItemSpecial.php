<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 9.5.15 19:10
 */

namespace Vegan\Bundle\MenuBundle\Menu;

/**
 * Class where you can store multiple information for menu, like any HTML variable (class="", data-href="", data-*="" e.g.)
 */
class MenuItemSpecial
{
    /** @var array $special */
    protected $special;

    public function add($key, $value)
    {
        if ($this->has($key)) {
            throw new \InvalidArgumentException("MenuItemSpecial already has value for key `{$key}`");
        }
        $this->special[$key] = $value;
    }

    public function addArray(array $array = array())
    {
        foreach ($array as $key => $value) {
            $this->add($key, $value);
        }
    }

    public function set($key, $value)
    {
        $this->special[$key] = $value;
    }

    public function setArray(array $array = array())
    {
        foreach ($array as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function has($key)
    {
        return array_key_exists($key, $this->special);
    }

    public function get($key)
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("MenuItemSpecial does not have key `{$key}`");
        }
        return $this->special[$key];
    }

    public function replace(array $array = array())
    {
        $this->special = $array;
    }

    public function reset()
    {
        $this->special = array();
    }

    /**
     * Magic method is becase you can call from template for example: {% if menu.special.class is defined %}class="{{ menu.special.class }}"{% endif %}
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->get($name);
    }
}
