<?php

namespace SilverStripe\Config\Collections;

use BadMethodCallException;
use SilverStripe\Config\Middleware\MiddlewareAware;
use Psr\Cache\CacheItemPoolInterface;

class CachedConfigCollection implements ConfigCollectionInterface
{
    use MiddlewareAware;

    /**
     * @const string
     */
    const CACHE_KEY = '__CACHE__';

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * Nested config to delegate to
     *
     * @var ConfigCollectionInterface
     */
    protected $collection;

    /**
     * @var callable
     */
    protected $collectionCreator;

    /**
     * @var bool
     */
    protected $flush = false;

    /**
     * Set to true while building config.
     * Used to protect against infinite loops.
     *
     * @var bool
     */
    protected $building = false;

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    public function get($class, $name = null, $excludeMiddleware = 0)
    {
        return $this->getCollection()->get($class, $name, $excludeMiddleware);
    }

    public function getAll()
    {
        return $this->getCollection()->getAll();
    }

    public function exists($class, $name = null, $excludeMiddleware = 0)
    {
        return $this->getCollection()->exists($class, $name, $excludeMiddleware);
    }

    public function getMetadata()
    {
        return $this->getCollection()->getMetadata();
    }

    public function getHistory()
    {
        return $this->getCollection()->getHistory();
    }

    /**
     * Get or build collection
     *
     * @return ConfigCollectionInterface
     */
    public function getCollection()
    {
        // Get current collection
        if ($this->collection) {
            return $this->collection;
        }

        // Init cached item
        $collectionCacheItem = $this->pool->getItem(self::CACHE_KEY);

        // Load from cache (unless flushing)
        if (!$this->flush && $collectionCacheItem->isHit()) {
            $this->collection = $collectionCacheItem->get();
            return $this->collection;
        }

        // Protect against infinity loop
        if ($this->building) {
            throw new BadMethodCallException("Infinite loop detected. Config could not be bootstrapped.");
        }
        $this->building = true;

        // Cache missed
        try {
            $this->collection = call_user_func($this->collectionCreator);
        } finally {
            $this->building = false;
        }

        // Note: Config may be yet modified prior to deferred save, but after Core.php
        // however no formal api for this yet
        $collectionCacheItem->set($this->collection);

        // Save immediately.
        // Note additional deferred save will occur in _destruct()
        $this->pool->save($collectionCacheItem);
        return $this->collection;
    }

    /**
     * Commits the cache
     */
    public function __destruct()
    {
        // Ensure back-end cache is updated
        if ($this->collection) {
            $cacheItem = $this->pool->getItem(self::CACHE_KEY);
            $cacheItem->set($this->collection);
            $this->pool->save($cacheItem);

            // Prevent double-destruct
            $this->collection = null;
        }
    }

    public function nest()
    {
        return $this->getCollection()->nest();
    }

    /**
     * Set a pool
     *
     * @param  CacheItemPoolInterface $pool
     * @return $this
     */
    public function setPool(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
        if ($this->flush) {
            $pool->clear();
        }
        return $this;
    }

    /**
     * @param callable $collectionCreator
     * @return $this
     */
    public function setCollectionCreator($collectionCreator)
    {
        $this->collectionCreator = $collectionCreator;
        return $this;
    }

    /**
     * @return callable
     */
    public function getCollectionCreator()
    {
        return $this->collectionCreator;
    }

    /**
     * @return CacheItemPoolInterface
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param bool $flush
     * @return $this
     */
    public function setFlush($flush)
    {
        $this->flush = $flush;
        if ($flush && $this->pool) {
            $this->pool->clear();
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getFlush()
    {
        return $this->flush;
    }

    public function setMiddlewares($middlewares)
    {
        throw new BadMethodCallException(
            "Please apply middleware to collection factory via setCollectionCreator()"
        );
    }
}
