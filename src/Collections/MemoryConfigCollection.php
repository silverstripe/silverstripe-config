<?php

namespace SilverStripe\Config\Collections;

use SilverStripe\Config\MergeStrategy\Priority;
use SilverStripe\Config\Middleware\MiddlewareAware;
use SilverStripe\Config\Transformer\TransformerInterface;
use Serializable;

/**
 * Basic mutable config collection stored in memory
 */
class MemoryConfigCollection implements MutableConfigCollectionInterface, Serializable
{
    use MiddlewareAware;

    /**
     * Stores a list of key/value config prior to middleware being applied
     *
     * @var array
     */
    protected $config = [];

    /**
     * Call cache for non-trivial config calls including middleware
     *
     * @var array
     */
    protected $callCache = [];

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var array
     */
    protected $history = [];

    /**
     * @var boolean
     */
    protected $trackMetadata = false;

    /**
     * ConfigCollection constructor.
     *
     * @param bool $trackMetadata
     */
    public function __construct($trackMetadata = false)
    {
        $this->trackMetadata = $trackMetadata;
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Trigger transformers to load into this store
     *
     * @param  TransformerInterface[] $transformers
     * @return $this
     */
    public function transform($transformers)
    {
        foreach ($transformers as $transformer) {
            $transformer->transform($this);
        }
        return $this;
    }

    public function set($class, $name, $data, $metadata = [])
    {
        $class = strtolower($class);
        $this->saveMetadata($class, $metadata);

        if ($name) {
            if (!isset($this->config[$class])) {
                $this->config[$class] = [];
            }
            $this->config[$class][$name] = $data;
        } else {
            $this->config[$class] = $data;
        }

        // Flush call cache for this class, and any subclasses
        unset($this->callCache[$class]);
        foreach ($this->callCache as $nextClass => $data) {
            if (is_subclass_of($nextClass, $class, true)) {
                unset($this->callCache[$nextClass]);
            }
        }
        return $this;
    }

    public function get($class, $name = null, $excludeMiddleware = 0)
    {
        if (!is_int($excludeMiddleware) && $excludeMiddleware !== true) {
            throw new \InvalidArgumentException("Invalid middleware flags");
        }

        // Get config for complete class
        $class = strtolower($class);
        $config = $this->getClassConfig($class, $excludeMiddleware);

        // Return either name, or whole-class config
        if ($name) {
            return isset($config[$name]) ? $config[$name] : null;
        }
        return $config;
    }

    /**
     * Retrieve config for an entire class
     *
     * @param string $class Name of class
     * @param int|true $excludeMiddleware Optional flag of middleware to disable.
     * Passing in `true` disables all middleware.
     * Can also pass in int flags to specify specific middlewares.
     * @return array|null
     */
    protected function getClassConfig($class, $excludeMiddleware = 0)
    {
        $class = strtolower($class);

        // Can't apply middleware to config on non-existant class
        if (!isset($this->config[$class])) {
            return null;
        }

        // `true` excludes all middleware, so bypass call cache
        if ($excludeMiddleware === true) {
            return $this->config[$class];
        }

        // Check cache
        if (isset($this->callCache[$class][$excludeMiddleware])) {
            return $this->callCache[$class][$excludeMiddleware];
        }

        // Build middleware
        $result = $this->callMiddleware(
            $class,
            $excludeMiddleware,
            function ($class, $excludeMiddleware) {
                $class = strtolower($class);
                return isset($this->config[$class]) ? $this->config[$class] : [];
            }
        );

        // Save cache
        if (!isset($this->callCache[$class])) {
            $this->callCache[$class] = [];
        }
        $this->callCache[$class][$excludeMiddleware] = $result;
        return $result;
    }

    public function exists($class, $name = null, $excludeMiddleware = 0)
    {
        $config = $this->get($class, null, $excludeMiddleware);
        if (!isset($config)) {
            return false;
        }
        if ($name) {
            return array_key_exists($name, $config);
        }
        return true;
    }

    public function remove($class, $name = null)
    {
        $class = strtolower($class);
        if ($name) {
            unset($this->config[$class][$name]);
        } else {
            unset($this->config[$class]);
        }
        // Discard call cache
        unset($this->callCache[$class]);
        return $this;
    }

    public function removeAll()
    {
        $this->config = [];
        $this->metadata = [];
        $this->history = [];
        $this->callCache = [];
    }

    /**
     * Get complete config (excludes middleware-applied config)
     *
     * @return array
     */
    public function getAll()
    {
        return $this->config;
    }

    /**
     * @deprecated 4.0...5.0
     *
     * Synonym for merge()
     *
     * @param string $class
     * @param string $name
     * @param mixed  $value
     * @return $this
     */
    public function update($class, $name, $value)
    {
        $this->merge($class, $name, $value);
        return $this;
    }

    public function merge($class, $name, $value)
    {
        // Detect mergeable config
        $existing = $this->get($class, $name, true);
        if (is_array($value) && is_array($existing)) {
            $value = Priority::mergeArray($value, $existing);
        }

        // Apply
        $this->set($class, $name, $value);
        return $this;
    }

    public function getMetadata()
    {
        if (!$this->trackMetadata || !is_array($this->metadata)) {
            return [];
        }

        return $this->metadata;
    }

    public function getHistory()
    {
        if (!$this->trackMetadata || !is_array($this->history)) {
            return [];
        }

        return $this->history;
    }

    public function serialize()
    {
        return serialize([
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata,
            $this->middlewares,
            $this->callCache
        ]);
    }

    public function unserialize($serialized)
    {
        list(
            $this->config,
            $this->history,
            $this->metadata,
            $this->trackMetadata,
            $this->middlewares,
            $this->callCache
        ) = unserialize($serialized);
    }

    public function nest()
    {
        return clone $this;
    }

    /**
     * Save metadata for the given class
     *
     * @param string $class
     * @param array  $metadata
     */
    protected function saveMetadata($class, $metadata)
    {
        if (!$this->trackMetadata) {
            return;
        }

        if (isset($this->metadata[$class]) && isset($this->config[$class])) {
            if (!isset($this->history[$class])) {
                $this->history[$class] = [];
            }

            array_unshift(
                $this->history[$class],
                [
                'value' => $this->config[$class],
                'metadata' => $this->metadata[$class]
                ]
            );
        }

        $this->metadata[$class] = $metadata;
    }
}
