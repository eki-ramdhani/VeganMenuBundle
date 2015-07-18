<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Menu;

use Doctrine\ORM\NoResultException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Doctrine\Common\Collections\ArrayCollection;

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

    /** @var ArrayCollection $attributes */
    private $attributes = null;


    /**
     * @param $anchor
     */
    public function __construct($anchor)
    {
        $this->anchor = $anchor;
        $this->children = array();
        $this->attributes = new ArrayCollection();
    }

    //----

    /** @return string */
    public function getAnchor()
    {
        return $this->anchor;
    }

    //----

    /** @return int|string */
    public function getId()
    {
        return $this->id;
    }

    /** @return bool */
    public function hasId()
    {
        return $this->id !== null;
    }

    /**
     * @param integer|string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    //----

    /** @return bool */
    public function isRoot()
    {
        return $this->parent === null;
    }

    /** @return null|MenuItem */
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

    /** @return null|MenuItem */
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

    /** @return MenuItem[] */
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

    /** @return null|MenuItem */
    public function getParent()
    {
        return $this->parent;
    }

    /** @return bool */
    public function hasParent()
    {
        return $this->parent !== null;
    }

    /**
     * @param MenuItem $item
     * @return $this
     */
    public function setParent(MenuItem $item)
    {
        $this->parent = $item;
        return $this;
    }

    //----

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /** @return bool */
    public function hasName()
    {
        return $this->name !== null;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    //----

    /** @return ArrayCollection */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /** @return bool */
    public function hasAttributes()
    {
        return $this->attributes->count() > 0;
    }

    /** @return $this */
    public function removeAttributes()
    {
        $this->attributes->clear();
        return $this;
    }

    /**
     * @param ArrayCollection $attributes
     * @return $this
     */
    public function replaceAttributes(ArrayCollection $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @param array|ArrayCollection $attributes
     * @param bool $rewrite
     * @return int
     */
    public function addAttributes($attributes, $rewrite = true)
    {
        $added = 0;
        foreach ($attributes as $key => $value) {
            if (true === $rewrite) {
                $this->attributes->set($key, $value);
                $added++;
            } else {
                $attr = $this->attributes->get($key);
                if (null === $attr) {
                    $this->attributes->set($key, $value);
                    $added++;
                }
            }
        }
        return $added;
    }

    /**
     * Get MenuItem attribute by `key`
     *
     * @param $attributeKey
     * @return mixed|null
     */
    public function get($attributeKey)
    {
        return $this->attributes->get($attributeKey);   // if attributeKey is not defined, then return null!
    }

    /**
     * Has MenuItem some attribute?
     *
     * @param $attributeKey
     * @return bool
     */
    public function has($attributeKey)
    {
        return $this->hasAttribute($attributeKey);
    }

    //----

    /**
     * Get attribute by key. Will throw Exception if attribute is null!
     * @param $attributeKey
     * @return mixed|null
     * @throws \InvalidArgumentException
     */
    public function getAttribute($attributeKey)
    {
        $attr = $this->attributes->get($attributeKey);
        if (null === $attr) {
            throw new \InvalidArgumentException("MenuItem `{$this->getAnchor()}` has no attribute with \$key `{$attributeKey}`");
        }
        return $attr;
    }

    /**
     * @param $attributeKey
     * @return bool
     */
    public function hasAttribute($attributeKey)
    {
        return $this->attributes->get($attributeKey) !== null;
    }

    /**
     * @param $attributeKey
     * @param $value
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addAttribute($attributeKey, $value)
    {
        $this->setAttribute($attributeKey, $value, false);
        return $this;
    }

    /**
     * @param $attributeKey
     * @param $value
     * @param bool|true $rewrite
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAttribute($attributeKey, $value, $rewrite = true)
    {
        $attr = $this->attributes->get($attributeKey);
        if (null !== $attr && false === $rewrite) {
            throw new \InvalidArgumentException("MenuItem `{$this->getAnchor()}` already has attribute `{$attributeKey}`");
        }
        $this->attributes->set($attributeKey, $value);
        return $this;
    }

    /**
     * @param $attributeKey
     * @return $this
     */
    public function removeAttribute($attributeKey)
    {
        $this->attributes->remove($attributeKey);
        return $this;
    }

    /**
     * @param $attributeKey
     * @return mixed|null
     * @throws \InvalidArgumentException
     */
    public function getAttr($attributeKey)
    {
        return $this->getAttribute($attributeKey);
    }

    /**
     * @param $attributeKey
     * @param $value
     * @param bool|true $rewrite
     * @return $this
     */
    public function setAttr($attributeKey, $value, $rewrite = true)
    {
        $this->setAttribute($attributeKey, $value, $rewrite);
        return $this;
    }

    /**
     * @param $attributeKey
     * @return bool
     */
    public function hasAttr($attributeKey)
    {
        return $this->hasAttribute($attributeKey);
    }

    /**
     * @param $attributeKey
     * @return $this
     */
    public function removeAttr($attributeKey)
    {
        $this->removeAttribute($attributeKey);
        return $this;
    }

    //----

    /**
     * @param MenuItem $item
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addChild(MenuItem $item)
    {
        if ($item->getAnchor() === 'root') {
            throw new \InvalidArgumentException("It's forbidden to add child `root` menu item in `$this->anchor` MenuItem");
        }
        if ($this->hasChild($item->getAnchor())) {
            throw new \InvalidArgumentException("MenuItem `$this->anchor` already has child with anchor `{$item->getAnchor()}`!");
        }
        $this->children[$item->getAnchor()] = $item;
        return $this;
    }

    /**
     * @param string $itemAnchor
     * @return bool
     */
    public function hasChild($itemAnchor)
    {
        return array_key_exists($itemAnchor, $this->children);
    }

    /**
     * @param $itemAnchor
     * @return bool|MenuItem
     */
    public function getChild($itemAnchor)
    {
        if (!$this->hasChild($itemAnchor)) {
            return false;
        }
        return $this->children[$itemAnchor];
    }

    /**
     * @param $itemAnchor
     * @return $this
     */
    public function removeChild($itemAnchor)
    {
        if ($this->hasChild($itemAnchor)) {
            unset($this->children[$itemAnchor]);
        }
        return $this;
    }

    //----

    /** @return MenuItem[] */
    public function getChildren()
    {
        return $this->children;
    }

    /** @return bool */
    public function hasChildren()
    {
        return (count($this->children) > 0);
    }

    /**
     * @param MenuItem[] $children
     * @return $this
     * @throws \InvalidArgumentException If some child is not instance of MenuItem
     */
    public function setChildren(array $children = array())
    {
        $result = array();
        foreach ($children as $child) {
            if (!($child instanceof MenuItem)) {
                throw new \InvalidArgumentException("You tried setChildren at MenuItem `{$this->getAnchor()}`, but some child is not instance of MenuItem.");
            }
            $result[$child->getAnchor()] = $child;
        }
        $this->children = $result;
        return $this;
    }

    /**
     * @param MenuItem[] $children Array of MenuItem
     * @param bool $rewrite Do you want rewrite adding $children?
     *
     * @return int Count of all added children
     */
    public function addChildren(array $children = array(), $rewrite = false)
    {
        $added = 0;
        foreach ($children as $child) {
            if (!($child instanceof MenuItem)) {
                continue;
            }
            if (true === $rewrite) {
                $this->children[$child->getAnchor()] = $child;
                $added++;
            } else {
                try {
                    $this->addChild($child);
                    $added++;
                } catch (\Exception $e) {}
            }
        }
        return $added;
    }

    /** @return $this */
    public function removeChildren()
    {
        $this->children = array();
        return $this;
    }

    //----

    /** @return null|string */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /** @return bool */
    public function hasRouteName()
    {
        return $this->routeName !== null;
    }

    /**
     * @param $routeName
     * @return $this
     */
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
        return $this;
    }

    //----

    /** @return null|string */
    public function getUri()
    {
        return $this->uri;
    }

    /** @return bool */
    public function hasUri()
    {
        return $this->uri !== null;
    }

    /**
     * @param $uri
     * @return $this
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * @param VeganUrlGenerator $generator
     * @param string $pathType
     * @return $this
     * @throws \InvalidArgumentException If route_name is not exists
     */
    public function generateUri(VeganUrlGenerator $generator, $pathType = UrlGenerator::RELATIVE_PATH)
    {
        $options = array(
            'slug' => $this->slug,
            'permalink' => $this->permalink,
        );

        if (!$this->hasRouteName()) {
            throw new \InvalidArgumentException('Impossible to generate MenuItem URI, missing route_name!');
        }

        $this->uri = $generator->generate($this->routeName, $options, $pathType);
        return $this;
    }

    //----

    /** @return null|string */
    public function getSlug()
    {
        return $this->slug;
    }

    /** @return bool */
    public function hasSlug()
    {
        return $this->slug !== null;
    }

    /**
     * @param $slug
     * @return $this
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
        return $this;
    }

    //----

    /** @return null|string */
    public function getPermalink()
    {
        return $this->permalink;
    }

    /** @return bool */
    public function hasPermalink()
    {
        return $this->permalink !== null;
    }

    /**
     * @param $permalink
     * @return $this
     */
    public function setPermalink($permalink)
    {
        $this->permalink = $permalink;
        return $this;
    }

    //----

    /** @return string */
    public function getLocale()
    {
        return $this->locale;
    }

    /** @return bool */
    public function hasLocale()
    {
        return $this->locale !== null;
    }

    /** @param string $locale */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    //----

    /** @return bool */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param bool|integer $status
     * @return $this
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
        return $this;
    }

    /** @return bool */
    public function getActive()
    {
        return $this->active;
    }

    //----

    /**
     * @param bool $autoGenerate
     * @return int|null
     */
    public function getLevel($autoGenerate = true)
    {
        if (true === $autoGenerate) {
            $this->generateLevel();
        }
        return $this->level;
    }

    /** @return bool */
    public function hasLevel()
    {
        return $this->level !== null;
    }

    /**
     * @param $level
     * @return $this
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /** @return $this */
    public function generateLevel()
    {
        $item = $this;
        $level = 0;
        while ($item->hasParent()) {
            $item = $item->getParent();
            $level++;
        }
        $this->level = $level;
        return $this;
    }

}
