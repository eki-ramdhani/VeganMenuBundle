<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="vegan_menu_item_attribute")
 *
 * Class VeganMenuItemAttribute for save special attributes for menu items
 */
class VeganMenuItemAttribute
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;


    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Vegan\MenuBundle\Entity\VeganMenuItem", inversedBy="attribute")
     *
     * @ORM\JoinTable(name="vegan_menu_item_attributes",
     *      joinColumns={
     *          @ORM\JoinColumn(name="attribute_id", referencedColumnName="id", onDelete="CASCADE")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="menu_item_id", referencedColumnName="id", onDelete="CASCADE")
     *      }
     *  )
     */
    protected $menuItem;


    /**
     * @var string
     *
     * @ORM\Column(name="locale", type="string", nullable=false, options={"default":"en_US"})
     */
    protected $locale = 'en_US';


    /**
     * @var string
     *
     * @ORM\Column(name="attribute", type="string", options={"comment":"Name of attribute, like class, alt, title or any other HTML attribute"})
     */
    protected $attribute;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", options={"comment":"Value of attribute e.g. `fa fa-icon`"})
     */
    protected $value;


    public function __construct()
    {
        $this->menuItem = new ArrayCollection();
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     * @return VeganMenuItemAttribute
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param string $attribute
     * @return VeganMenuItemAttribute
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return VeganMenuItemAttribute
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }


    /**
     * @return \Doctrine\ORM\PersistentCollection
     */
    public function getMenuItems()
    {
        return $this->menuItem;
    }


    public function clearMenuItems()
    {
        $this->menuItem = null;
    }

}
