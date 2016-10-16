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
            $cachedMetadata = $this->getMetadata();
            $cachedHistory = $this->getHistory();

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
        return $this->getTrackingData(self::METADATA_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        return $this->getTrackingData(self::HISTORY_KEY);
    }

    /**
     * A shortcut for tracking data (metadata and history). This will
     * always return an array, even if we're not tracking.
     *
     * @param string $key
     *
     * @return array
     */
    private function getTrackingData($key)
    {
        if (!$this->trackMetadata) {
            return [];
        }

        $value = $this->pool->getItem($key)->get();

        return is_array($value) ? $value : [];
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
