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

    protected $journal = null;

    /**
     * @param string|null $folder
     */
    public function __construct($folder = null)
    {
        if (null === $folder) {
            $folder = $this->getDefaultFolder();    // default folder: /app/cache/dev|prod/nette
        }
        try {
            $this->journal = new FileJournal($folder);      // IJournal is required for Cache::TAGS
            $this->storage = new FileStorage($folder, $this->journal);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Directory {$folder} was not found! You can omit an argument \$folder and the default folder will be used (/app/cache/[dev|prod]/nette");
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

    /**
     * Not so clean method, but really powerfull (we don't need to inject $kernel or $container to this service
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        global $kernel;
        if ($kernel instanceof \AppCache) {
            $kernel = $kernel->getKernel();
        }
        if ($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface) {
            $folder = $kernel->getCacheDir().'/nette';
        } else {
            throw new \LogicException("Global variable \$kernel is not instace of KernelInterface.");
        }
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        return $folder;
    }
}
