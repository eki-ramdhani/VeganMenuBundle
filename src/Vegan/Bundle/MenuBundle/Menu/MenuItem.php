<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\Bundle\MenuBundle\Menu;

use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Single menu item object
 */
class MenuItem
{
    /** @var string $anchor Unique anchor which helps to 'catch' menu item */
    private $anchor;

    /** @var integer|string $id If you want load ID from database */
    private $id = null;

    /** @var MenuItem|null $parent Has this item some parent? */
    private $parent = null;

    /** @var string $name Name (label) of MenuItem which will display in frontend */
    private $name;

    /** @var string|null $routeName  */
    private $routeName = null;

    /** @var string|null $slug */
    private $slug = null;

    /** @var string|null $permalink */
    private $permalink = null;

    /** @var string|null $uri */
    private $uri = null;

    /** @var string $locale */
    private $locale = 'en_US';

    /** @var MenuItem[] */
    private $children;

    /** @var bool $active */
    private $active = false;

    /** @var integer|null $level Tree level => root has level 0, first inserted MenuItem has 1 */
    private $level = null;

    /** @var MenuItemSpecial $special */
    private $special = null;

    public function __construct($anchor)
    {
        $this->anchor = $anchor;
        $this->children = array();
    }


    public function isRoot()
    {
        return $this->parent === null;
    }

    public function getRoot()
    {
        $item = $this->getParent();
        if ($item->getAnchor() === 'root') {
            return $this;
        }
        do {
            $item = $item->getParent();
        } while ($item->hasParent());

        return $item;
    }

    public function getRootParent()
    {
        $item = $this->getParent();
        if ($item->getAnchor() === 'root') {
            return null;
        }
        do {
            if (!$item->hasParent() || $item->getParent()->getAnchor() === 'root') {
                break;
            }
            $item = $item->getParent();
        } while ($item->hasParent());

        return $item;
    }

    public function getParents()
    {
        $result = array();
        $item = $this->getParent();
        if ($item->getAnchor() === 'root') {
            return $result;
        }
        do {
            if (!$item->hasParent() || $item->getParent()->getAnchor() === 'root') {
                break;
            } else {
                $result[$item->getAnchor()] = $item;
            }
            $item = $item->getParent();
        } while ($item->hasParent());
        return $result;
    }

    public function getAnchor()
    {
        return $this->anchor;
    }

    public function getName()
    {
        return $this->name;
    }

    public function hasName()
    {
        return $this->name !== null;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function addChild(MenuItem $item)
    {
        if ($item->getAnchor() === 'root') {
            throw new \InvalidArgumentException("It's forbidden to add child `root` menu item in `$this->anchor` MenuItem");
        }
        if ($this->hasChild($item->getAnchor())) {
            throw new \InvalidArgumentException("MenuItem `$this->anchor` already has child with anchor `{$item->getAnchor()}`!");
        }
        $this->children[$item->getAnchor()] = $item;
    }

    public function hasChild($anchor)
    {
        return array_key_exists($anchor, $this->children);
    }

    public function getChild($anchor)
    {
        if (!$this->hasChild($anchor)) {
            return false;
        }
        return $this->children[$anchor];
    }

    public function removeChild($anchor)
    {
        if ($this->hasChild($anchor)) {
            unset($this->children[$anchor]);
        }
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return $this->parent !== null;
    }

    public function setParent(MenuItem $item)
    {
        $this->parent = $item;
    }

    /**
     * @return MenuItem[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return (count($this->children) > 0);
    }

    /**
     * @param MenuItem[] $children Array of MenuItem
     * @param bool $rewrite
     */
    public function addChildren(array $children = array(), $rewrite = false)
    {
        foreach ($children as $child) {
            if (!($child instanceof MenuItem)) {
                continue;
            }
            if (true === $rewrite) {
                $this->children[$child->getAnchor()] = $child;
            } else {
                try {
                    $this->addChild($child);
                } catch (\Exception $e) {}
            }
        }
    }

    public function removeChildren()
    {
        $this->children = array();
    }

    public function getRouteName()
    {
        return $this->routeName;
    }

    public function hasRouteName()
    {
        return $this->routeName !== null;
    }

    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    public function hasUri()
    {
        return $this->uri !== null;
    }

    /**
     * @param VeganUrlGenerator $generator
     * @param string $pathType
     */
    public function generateUri(VeganUrlGenerator $generator, $pathType = UrlGenerator::RELATIVE_PATH)
    {
        $options = array(
            'slug' => $this->slug,
            'permalink' => $this->permalink,
        );

        if (!$this->hasRouteName()) {
            throw new \InvalidArgumentException("Impossible to generate MenuItem URI, missing route_name!");
        }

        $uri = $generator->generate($this->routeName, $options, $pathType);
        dump($uri);
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function hasSlug()
    {
        return $this->slug !== null;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function getPermalink()
    {
        return $this->permalink;
    }

    public function hasPermalink()
    {
        return $this->permalink !== null;
    }

    public function setPermalink($permalink)
    {
        $this->permalink = $permalink;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Is MenuItem active?
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Set MenuItem as active or inactive (true | false)
     *
     * @param boolean|integer $status Value must be one of: true|false|0|1
     */
    public function setActive($status = true)
    {
        if (is_int($status) && ($status === 1 || $status === 0)) {
            $status = (bool)$status;
        }
        if (!is_bool($status)) {
            throw new \InvalidArgumentException("MenuItem method `setActive` must be boolean type inside MenuItem anchor: `{$this->getAnchor()}`");
        }
        $this->active = $status;
    }

    /**
     * Get status of MenuItem
     *
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    public function getLevel()
    {
        $this->generateLevel();
        return $this->level;
    }

    public function generateLevel()
    {
        $item = $this;
        $level = 0;
        while ($item->hasParent()) {
            $item = $item->getParent();
            $level++;
        }
        $this->level = $level;
    }


    public function getSpecial()
    {
        return $this->special;
    }

    public function hasSpecial()
    {
        return $this->special !== null;
    }

    public function setSpecial(MenuItemSpecial $special)
    {
        if (!$this->hasSpecial()) {
            $this->createSpecial();
        }

        $this->special = $special;
    }

    public function createSpecial()
    {
        $this->special = new MenuItemSpecial();
    }


    public function addSpecialValue($key, $value, $rewrite = false)
    {
        if (!$this->hasSpecial()) {
            $this->createSpecial();
        }
        if ($this->special->has($key) && false === $rewrite) {
            throw new \InvalidArgumentException("MenuItem `{$this->getAnchor()}` already has special with key `{$key}`");
        }
        if (true === $rewrite) {
            $this->special->set($key, $value);
        } else {
            $this->special->add($key, $value);
        }
    }

    public function addSpecialArray(array $array = array(), $rewrite = false)
    {
        if (!$this->hasSpecial()) {
            $this->createSpecial();
        }
        if (count($array) === 0) {
            return;
        }
        if (true === $rewrite) {
            $this->special->setArray($array);
        } else {
            $this->special->addArray($array);
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function hasId()
    {
        return $this->id !== null;
    }

}
