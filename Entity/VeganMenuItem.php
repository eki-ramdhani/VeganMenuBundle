<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * VeganMenuItem
 *
 * @ORM\Table(name="vegan_menu_item", uniqueConstraints={@ORM\UniqueConstraint(name="idx_vegan_menu_item_0", columns={"anchor"})}, indexes={@ORM\Index(name="idx_vegan_menu_item", columns={"parent_id"}), @ORM\Index(name="idx_vegan_menu_item_1", columns={"tree_left"}), @ORM\Index(name="idx_vegan_menu_item_2", columns={"tree_right"}), @ORM\Index(name="idx_vegan_menu_item_3", columns={"tree_level"}), @ORM\Index(name="idx_vegan_menu_item_4", columns={"is_active"})})
 * @ORM\Entity
 */
class VeganMenuItem
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
     * @var string
     *
     * @ORM\Column(name="anchor", type="string", length=255, nullable=false)
     */
    private $anchor;

    /**
     * @var integer
     *
     * @ORM\Column(name="tree_left", type="integer", nullable=true, options={"default":null})
     */
    private $treeLeft;

    /**
     * @var integer
     *
     * @ORM\Column(name="tree_right", type="integer", nullable=true, options={"default":null})
     */
    private $treeRight;

    /**
     * @var integer
     *
     * @ORM\Column(name="tree_level", type="integer", nullable=false, options={"default":1})
     */
    private $treeLevel = '1';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true, options={"default":null})
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true, options={"default": null})
     */
    private $updatedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true, options={"default":null})
     */
    private $deletedAt;

    /**
     * @var integer
     *
     * @ORM\Column(name="is_active", type="smallint", nullable=false, options={"default":1})
     */
    private $isActive = '1';

    /**
      * @ORM\OneToMany(targetEntity="VeganMenuItem", mappedBy="parent")
      */
    private $children;

    /**
      * @ORM\ManyToOne(targetEntity="VeganMenuItem", inversedBy="children")
      * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
      */
    private $parent;

    /**
     * @var VeganMenu
     *
     * @ORM\ManyToOne(targetEntity="VeganMenu", inversedBy="item")
     *
     * @ORM\JoinTable(name="vegan_menu",
     *          joinColumns={
     *              @ORM\JoinColumn(name="menu_id", referencedColumnName="id")
     *          }
     *      )
     */
    private $menu;

    /**
     * @ORM\ManyToMany(targetEntity="Vegan\MenuBundle\Entity\VeganMenuItemTranslation", orphanRemoval=true, cascade={"remove","persist","refresh"})
     *
     * @ORM\JoinTable(name="vegan_menu_item_translations",
     *          joinColumns={
     *              @ORM\JoinColumn(name="menu_item_id", referencedColumnName="id", onDelete="CASCADE")
     *          },
     *          inverseJoinColumns={
     *              @ORM\JoinColumn(name="translation_id", referencedColumnName="id", onDelete="CASCADE")
     *          }
     *      )
     **/
    private $translation;


    /**
     * @ORM\ManyToMany(targetEntity="Vegan\MenuBundle\Entity\VeganMenuItemAttribute", orphanRemoval=true, cascade={"all"}, mappedBy="menuItem", orphanRemoval=true)
     */
    private $attribute;




    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set anchor
     *
     * @param string $anchor
     * @return VeganMenuItem
     */
    public function setAnchor($anchor)
    {
        $this->anchor = $anchor;

        return $this;
    }

    /**
     * Get anchor
     *
     * @return string
     */
    public function getAnchor()
    {
        return $this->anchor;
    }

    /**
     * Set treeLeft
     *
     * @param integer $treeLeft
     * @return VeganMenuItem
     */
    public function setTreeLeft($treeLeft)
    {
        $this->treeLeft = $treeLeft;

        return $this;
    }

    /**
     * Get treeLeft
     *
     * @return integer
     */
    public function getTreeLeft()
    {
        return $this->treeLeft;
    }

    /**
     * Set treeRight
     *
     * @param integer $treeRight
     * @return VeganMenuItem
     */
    public function setTreeRight($treeRight)
    {
        $this->treeRight = $treeRight;

        return $this;
    }

    /**
     * Get treeRight
     *
     * @return integer
     */
    public function getTreeRight()
    {
        return $this->treeRight;
    }

    /**
     * Set treeLevel
     *
     * @param integer $treeLevel
     * @return VeganMenuItem
     */
    public function setTreeLevel($treeLevel)
    {
        $this->treeLevel = $treeLevel;

        return $this;
    }

    /**
     * Get treeLevel
     *
     * @return integer
     */
    public function getTreeLevel()
    {
        return $this->treeLevel;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return VeganMenuItem
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return VeganMenuItem
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set deletedAt
     *
     * @param \DateTime $deletedAt
     * @return VeganMenuItem
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Get deletedAt
     *
     * @return \DateTime
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Set isActive
     *
     * @param integer $isActive
     * @return VeganMenuItem
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Get isActive
     *
     * @return integer
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * Set parent
     *
     * @param VeganMenuItem $parent
     * @return VeganMenuItem
     */
    public function setParent(VeganMenuItem $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return VeganMenuItem
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return VeganMenuItemTranslation
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * @param mixed $translation
     *
     * @return VeganMenuItem
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param mixed $children
     *
     * @return VeganMenuItem
     */
    public function setChildren($children)
    {
        $this->children = $children;

        return $this;
    }

    public function getMenu()
    {
        return $this->menu;
    }

    /**
     * @param VeganMenu $menu
     *
     * @return VeganMenuItem
     */
    public function setMenu(VeganMenu $menu)
    {
        $this->menu = $menu;

        return $this;
    }

    /**
     * @return VeganMenuItemAttribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param VeganMenuItemAttribute $attribute
     * @return VeganMenuItem
     */
    public function setAttribute(VeganMenuItemAttribute $attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }
}
