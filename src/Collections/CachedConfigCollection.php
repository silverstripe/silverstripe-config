<?php

namespace SilverStripe\Config\Collections;

use BadMethodCallException;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Config\Middleware\MiddlewareAware;

class CachedConfigCollection implements ConfigCollectionInterface
{
    use MiddlewareAware;

    /**
     * @const string
     */
    const CACHE_KEY = '__CACHE__';

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Nested config to delegate to
     *
     * @var ConfigCollectionInterface
     */
    protected $collection;

    /**
     * Stores a hash to allow comparison
     */
    private ?string $collectionHash = null;

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
     * Injectable factory for nesting config.
     * This callback will be passed the inner ConfigCollection
     *
     * @var callable
     */
    protected $nestFactory = null;

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Get callback for nesting the inner collection
     *
     * @return callable
     */
    public function getNestFactory()
    {
        return $this->nestFactory;
    }

    /**
     * Set callback for nesting the inner collection
     *
     * @param callable $factory
     * @return $this
     */
    public function setNestFactory(callable $factory)
    {
        $this->nestFactory = $factory;
        return $this;
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

    private function getHash(): string
    {
        return md5(serialize($this->collection));
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

        // Load from cache (unless flushing)
        if (!$this->flush) {
            $this->collection = $this->cache->get(CachedConfigCollection::CACHE_KEY);
            if ($this->collection) {
                $this->collectionHash = $this->getHash();
                return $this->collection;
            }
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

        // Save immediately.
        // Note additional deferred save can occur in _destruct()
        $this->cache->set(CachedConfigCollection::CACHE_KEY, $this->collection);
        $this->collectionHash = $this->getHash();
        return $this->collection;
    }

    /**
     * Commits the cache
     */
    public function __destruct()
    {
        // Ensure back-end cache is updated
        if ($this->collection && $this->collectionHash) {
            if ($this->getHash() !== $this->collectionHash) {
                $this->cache->set(CachedConfigCollection::CACHE_KEY, $this->collection);
            }

            // Prevent double-destruct
            $this->collection = null;
            $this->collectionHash = null;
        }
    }

    public function nest()
    {
        $collection = $this->getCollection();
        $factory = $this->getNestFactory();
        if ($factory) {
            return $factory($collection);
        }

        // Fall back to regular nest
        return $collection->nest();
    }

    /**
     * Set a PSR-16 cache
     *
     * @param CacheInterface $cache
     * @return $this
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
        if ($this->flush) {
            $cache->clear();
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
     * @return CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param bool $flush
     * @return $this
     */
    public function setFlush($flush)
    {
        $this->flush = $flush;
        if ($flush && $this->cache) {
            $this->cache->clear();
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
