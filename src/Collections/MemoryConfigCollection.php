<?php

namespace SilverStripe\Config\Collections;

use SilverStripe\Config\MergeStrategy\Priority;
use SilverStripe\Config\Middleware\MiddlewareAware;
use SilverStripe\Config\Transformer\TransformerInterface;
use SilverStripe\Dev\Deprecation;

/**
 * Basic mutable config collection stored in memory
 */
class MemoryConfigCollection implements MutableConfigCollectionInterface
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

    public function set(string $class, ?string $name, mixed $data, array $metadata = []): static
    {
        $this->saveMetadata($class, $metadata);

        $classKey = strtolower($class ?? '');
        if ($name) {
            $this->checkForDeprecatedConfig($classKey, $name);
            if (!isset($this->config[$classKey])) {
                $this->config[$classKey] = [];
            }
            $this->config[$classKey][$name] = $data;
        } else {
            $this->config[$classKey] = $data;
        }

        // Flush call cache
        $this->callCache = [];
        return $this;
    }

    public function get($class, $name = null, $excludeMiddleware = 0)
    {
        if (!is_int($excludeMiddleware) && $excludeMiddleware !== true) {
            throw new \InvalidArgumentException("Invalid middleware flags");
        }

        // Get config for complete class
        $config = $this->getClassConfig($class, $excludeMiddleware);

        // Return either name, or whole-class config
        if ($name) {
            $this->checkForDeprecatedConfig($class, $name);
            return isset($config[$name]) ? $config[$name] : null;
        }
        return $config;
    }

    public function checkForDeprecatedConfig($class, $name): void
    {
        $deprecated = $this->getClassConfig('__deprecated', true);
        $data = $deprecated['config'][strtolower($class)][$name] ?? [];
        if (!empty($data)) {
            if (class_exists(Deprecation::class)) {
                Deprecation::notice($data['version'], $data['message'], Deprecation::SCOPE_CONFIG);
            } else {
                user_error($data['message'], E_USER_DEPRECATED);
            }
        }
    }

    /**
     * Retrieve config for an entire class
     *
     * @param string $class Name of class
     * @param int|true $excludeMiddleware Optional flag of middleware to disable.
     * Passing in `true` disables all middleware.
     * Can also pass in int flags to specify specific middlewares.
     * @return array
     */
    protected function getClassConfig($class, $excludeMiddleware = 0)
    {
        // `true` excludes all middleware, so bypass call cache
        $classKey = strtolower($class ?? '');
        if ($excludeMiddleware === true) {
            return isset($this->config[$classKey]) ? $this->config[$classKey] : [];
        }

        // Check cache
        if (isset($this->callCache[$classKey][$excludeMiddleware])) {
            return $this->callCache[$classKey][$excludeMiddleware];
        }

        // Build middleware
        $result = $this->callMiddleware(
            $class,
            $excludeMiddleware,
            function ($class, $excludeMiddleware) {
                return $this->getClassConfig($class, true);
            }
        );

        // Save cache
        if (!isset($this->callCache[$classKey])) {
            $this->callCache[$classKey] = [];
        }
        $this->callCache[$classKey][$excludeMiddleware] = $result;
        return $result;
    }

    public function exists($class, $name = null, $excludeMiddleware = 0)
    {
        $config = $this->get($class, null, $excludeMiddleware);
        if (empty($config)) {
            return false;
        }
        if ($name) {
            return array_key_exists($name, $config ?? []);
        }
        return true;
    }

    public function remove(string $class, ?string $name = null): static
    {
        $classKey = strtolower($class ?? '');
        if ($name) {
            unset($this->config[$classKey][$name]);
        } else {
            unset($this->config[$classKey]);
        }
        // Discard call cache
        unset($this->callCache[$classKey]);
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

    public function merge(string $class, string|null $name, array $value): static
    {
        // Detect mergeable config
        $existing = $this->get($class, $name, true);
        if (is_array($existing)) {
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

    /**
     * Get list of serialized properties
     *
     * @return array
     */
    protected function getSerializedMembers()
    {
        return array_filter(array_keys(get_object_vars($this)), function ($key) {
            // Skip $_underscoreProp
            return strpos($key ?? '', '_') !== 0;
        });
    }

    public function __serialize(): array
    {
        $data = [];
        foreach ($this->getSerializedMembers() as $key) {
            $data[$key] = $this->$key;
        }
        return $data;
    }

    public function __unserialize(array $data): void
    {
        foreach ($this->getSerializedMembers() as $key) {
            $this->$key = isset($data[$key]) ? $data[$key] : null;
        }
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

        $classKey = strtolower($class ?? '');
        if (isset($this->metadata[$classKey]) && isset($this->config[$classKey])) {
            if (!isset($this->history[$classKey])) {
                $this->history[$classKey] = [];
            }

            array_unshift(
                $this->history[$classKey],
                [
                'value' => $this->config[$classKey],
                'metadata' => $this->metadata[$classKey]
                ]
            );
        }

        $this->metadata[$classKey] = $metadata;
    }
}
