<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 */

namespace Vegan\MenuBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * VeganMenuTranslation
 *
 * @ORM\Table(name="vegan_menu_translation", indexes={@ORM\Index(name="vegan_menu_translation_1", columns="deleted_at"), @ORM\Index(name="vegan_menu_translation_2", columns="is_active")})
 * @ORM\Entity
 */
class VeganMenuTranslation
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
     * @ORM\Column(name="locale", type="string", length=5, nullable=true, options={"default":"en_US"})
     */
    private $locale = 'en_US';

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=100, nullable=false, options={"comment":"Menu translation"})
     */
    private $name;

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
     * @var string
     *
     * @ORM\Column(name="default_route", type="string", nullable=false, options={"comment":"Set default route name for Menu"})
     */
    private $defaultRoute;


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
     * Set locale
     *
     * @param string $locale
     * @return VeganMenuTranslation
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return VeganMenuTranslation
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set isActive
     *
     * @param integer $isActive
     * @return VeganMenuTranslation
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
     * @return VeganMenuTranslation
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
     * @return VeganMenuTranslation
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
     * @return VeganMenuTranslation
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
     * @return string
     */
    public function getDefaultRoute()
    {
        return $this->defaultRoute;
    }

    /**
     * @param string $defaultRoute
     * @return VeganMenu
     */
    public function setDefaultRoute($defaultRoute)
    {
        $this->defaultRoute = $defaultRoute;

        return $this;
    }
}
