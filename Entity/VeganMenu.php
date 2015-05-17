<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * VeganMenu
 *
 * @ORM\Table(name="vegan_menu", uniqueConstraints={@ORM\UniqueConstraint(name="idx_vegan_menu_0", columns={"anchor"})})
 * @ORM\Entity
 */
class VeganMenu
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
     * @ORM\Column(name="anchor", type="string", length=50, nullable=false, options={"comment":"unique menu identifier called `anchor`"})
     */
    private $anchor;

    /**
     * @var integer
     *
     * @ORM\Column(name="is_active", type="smallint", nullable=false, options={"default":1})
     */
    private $isActive = '1';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true, options={"default":null})
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true, options={"default":null})
     */
    private $updatedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true, options={"default":null})
     */
    private $deletedAt;

    /**
     * @ORM\ManyToMany(targetEntity="Vegan\MenuBundle\Entity\VeganMenuTranslation", cascade={"remove","persist","refresh"})
     *
     * @ORM\JoinTable(name="vegan_menu_translations",
     *          joinColumns={
     *              @ORM\JoinColumn(name="menu_id", referencedColumnName="id", onDelete="CASCADE")
     *          },
     *          inverseJoinColumns={
     *              @ORM\JoinColumn(name="translation_id", referencedColumnName="id", onDelete="CASCADE")
     *          }
     *      )
     **/
    private $translation;

    /**
     * @var VeganMenuItem
     *
     * @ORM\OneToMany(targetEntity="VeganMenuItem", mappedBy="menu")
     */
    private $item;



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
     * Set isActive
     *
     * @param integer $isActive
     * @return VeganMenu
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
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return VeganMenu
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
     * @return VeganMenu
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
     * @return VeganMenu
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
     * Set anchor
     *
     * @param string $anchor
     * @return VeganMenu
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
     * @return VeganMenuTranslation
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * @param VeganMenuTranslation $translation
     *
     * @return VeganMenu
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * @return VeganMenuItem
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * @param VeganMenuItem $item
     *
     * @return VeganMenu
     */
    public function setItem($item)
    {
        $this->item = $item;

        return $this;
    }

}
