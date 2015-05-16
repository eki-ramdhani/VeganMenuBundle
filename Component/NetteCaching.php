<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 12.5.15 10:53
 */

namespace Vegan\MenuBundle\Component;

use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Storages\FileJournal;

class NetteCaching
{
    /** @var FileStorage $storage */
    protected $storage = null;

    /** @var Cache $cache */
    protected $cache = null;

    /** @var FileJournal|null $journal */
    protected $journal = null;

    /** @var string $folder */
    protected $folder = null;

    /**
     * @param string|null $folder
     */
    public function __construct($folder)
    {
        $this->folder = $folder;
        try {
            $this->journal = new FileJournal($this->folder);      // IJournal is required for Cache::TAGS
            $this->storage = new FileStorage($this->folder, $this->journal);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Directory {$this->folder} was not found!");
        }
        $this->cache = new Cache($this->storage);
    }

    /**
     * @return FileStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @return bool
     */
    public function hasStorage()
    {
        return $this->storage !== null;
    }

    /**
     * @param FileStorage $storage
     */
    public function setStorage(FileStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return bool
     */
    public function hasCache()
    {
        return $this->cache !== null;
    }

    /**
     * @param Cache $cache
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

}
