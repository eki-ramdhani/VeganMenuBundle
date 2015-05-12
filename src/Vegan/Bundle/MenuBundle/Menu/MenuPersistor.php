<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 9.5.15 18:46
 */

namespace Vegan\Bundle\MenuBundle\Menu;

// TODO: dodělat Persistor, který bude umět ukládat struktury Menu

use Doctrine\DBAL\Connection;

class MenuPersistor
{
    /** @var Connection $connection */
    private $connection;

    /** @var MenuBuilder $menuBuilder */
    private $menuBuilder;

    /** @var MenuCollection $menuCollection */
    private $menuCollection;

    /**
     * @param Connection $connection
     * @param MenuCollection $collection
     */
    public function __construct(Connection $connection, MenuCollection $collection)
    {
        $this->connection = $connection;
        $this->menuCollection = $collection;
        $this->menuBuilder = new MenuBuilder();
    }


    public function getMenu($menuAnchor)
    {
        return $this->menuCollection->getMenu($menuAnchor);
    }


    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
