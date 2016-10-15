<?php

namespace micmania1\config;

use Psr\Cache\CacheItemPoolInterface;

class CachedConfigCollection implements ConfigCollectionInterface
{
    /**
     * @const string
     */
    const METADATA_KEY = '__METADATA__';

    /**
     * @const string
     */
    const HISTORY_KEY = '__HISTORY__';

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * @var boolean
     */
    protected $trackMetadata = false;

    /**
     * @param boolean $trackMetadata
     * @param CacheItemPoolInterface $pool
     */
    public function __construct(CacheItemPoolInterface $pool, $trackMetadata = false)
    {
        $this->pool = $pool;
        $this->trackMetadata = (bool) $trackMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $metadata = [])
    {
        // We use null as the key to return an empty cache item
        $cacheItem = $this->pool->getItem($key);

        if($this->trackMetadata) {
            $cachedMetadata= $this->getMetadata();
            $cachedHistory= $this->getHistory();

            if($this->exists($key) && isset($metadata[$key])) {
                array_unshift($cachedHistory, [
                    'value' => $value,
                    'metadata' => $metadata,
                ]);
            }

            $cachedMetadata[$key] = $metadata;

            $this->saveMetadata($cachedMetadata);
            $this->saveHistory($cachedHistory);
        }

        // Save our new value
        $cacheItem->set($value);
        $this->pool->saveDeferred($cacheItem);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if(!$this->exists($key)) {
            return null;
        }

        $cacheItem = $this->pool->getItem($key);

        return $cacheItem->get() ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return $this->pool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        $metadata = $this->pool->getItem(self::METADATA_KEY)->get();

        if(!is_array($metadata)) {
            $metadata = [];
        }

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        $history = $this->pool->getItem(self::HISTORY_KEY);

        if(!is_array($history)) {
            $history = [];
        }

        return $history;
    }

    /**
     * Saves the metadata to cache
     *
     * @param array $metadata
     */
    protected function saveMetadata($metadata)
    {
        $cached = $this->pool->getItem(self::METADATA_KEY);
        $cached->set($metadata);

        $this->pool->saveDeferred($cached);
    }

    /**
     * Saves the history to the cache
     *
     * @param array $history
     */
    protected function saveHistory($history)
    {
        $cached = $this->pool->getItem(self::HISTORY_KEY);
        $cached->set($history);

        $this->pool->saveDeferred($cached);
    }

    /**
     * Commits the cache
     */
    public function __destruct()
    {
        $this->pool->commit();
    }
}
