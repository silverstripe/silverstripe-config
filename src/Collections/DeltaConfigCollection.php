<?php

namespace SilverStripe\Config\Collections;

use SilverStripe\Config\Middleware\DeltaMiddleware;

/**
 * Applies config modifications as a set of deltas on top of the
 * middleware, instead of as modifications to the underlying list.
 */
class DeltaConfigCollection extends MemoryConfigCollection
{
    /**
     * Remove delta
     */
    const REMOVE = 'remove';

    /**
     * Merge delta
     */
    const MERGE = 'merge';

    /**
     * Remove all config for this class
     */
    const CLEAR = 'clear';

    /**
     * Replace all config for this class
     */
    const REPLACE = 'replace';

    /**
     * Set delta
     */
    const SET = 'set';

    /**
     * @var DeltaMiddleware
     */
    protected $deltaMiddleware = null;

    /**
     * List of deltas keyed by class
     *
     * @var array
     */
    protected $deltas = [];

    /**
     * True if removeAll() is applied
     *
     * @var bool
     */
    protected $deltaReset = false;

    /**
     * Construct a delta collection
     *
     * @param bool $trackMetadata Set to true to track metadata
     * @param int $middlewareFlag Flag to use to disable delta middleware (optional)
     */
    public function __construct($trackMetadata = false, $middlewareFlag = 0)
    {
        parent::__construct($trackMetadata);
        $this->deltaMiddleware = new DeltaMiddleware($this, $middlewareFlag);
        $this->postInit();
    }

    /**
     * Create a delta collection from a parent collection
     *
     * @param ConfigCollectionInterface $parent
     * @param int $middlewareFlag Flag to use to disable delta middleware (optional)
     * @return static
     */
    public static function createFromCollection(ConfigCollectionInterface $parent, $middlewareFlag = 0)
    {
        // Copy properties to subclass
        $collection = new static();
        foreach (get_object_vars($parent) as $key => $value) {
            $collection->$key = $value;
        }

        // Set middleware flag
        $collection
            ->getDeltaMiddleware()
            ->setDisableFlag($middlewareFlag);

        // Ensure back-links are re-created between middleware and collection
        $collection->postInit();
        return $collection;
    }

    /**
     * Get middleware for handling deltas
     *
     * @return DeltaMiddleware
     */
    public function getDeltaMiddleware()
    {
        return $this->deltaMiddleware;
    }

    public function getMiddlewares()
    {
        $middlewares = parent::getMiddlewares();
        array_unshift($middlewares, $this->getDeltaMiddleware());
        return $middlewares;
    }

    /**
     * Get deltas for the given class
     *
     * @param string $class
     * @return array
     */
    public function getDeltas($class)
    {
        $classKey = strtolower($class ?? '');
        return isset($this->deltas[$classKey])
            ? $this->deltas[$classKey]
            : [];
    }

    /**
     * Check if config should be completely reset before getting config
     *
     * @param string $class Class to check
     * @return bool
     */
    public function isDeltaReset($class = null)
    {
        // Global reset
        if ($this->deltaReset) {
            return true;
        }

        // Note: Deltas of the given type reset the prior config
        $deltas = $this->getDeltas($class);
        return isset($deltas[0]['type'])
            && in_array(
                $deltas[0]['type'],
                [DeltaConfigCollection::REPLACE, DeltaConfigCollection::CLEAR]
            );
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->postInit();
    }

    public function __clone()
    {
        $this->deltaMiddleware = clone $this->deltaMiddleware;
        $this->postInit();
    }

    /**
     * Restore back-links post-creation or unserialization
     */
    protected function postInit()
    {
        // Ensure backlink to middleware
        $this->getDeltaMiddleware()->setCollection($this);
    }

    public function set(string $class, ?string $name, mixed $data, array $metadata = []): static
    {
        // Check config to merge
        $this->clearDeltas($class, $name);
        if ($name) {
            $this->addDelta($class, [
                'type' => DeltaConfigCollection::SET,
                'config' => [$name => $data],
            ]);
        } else {
            $this->addDelta($class, [
                'type' => DeltaConfigCollection::REPLACE,
                'config' => $data,
            ]);
        }
        return $this;
    }

    public function remove(string $class, ?string $name = null): static
    {
        // Check config to merge
        $this->clearDeltas($class, $name);
        if ($name) {
            $this->addDelta($class, [
                'type' => DeltaConfigCollection::REMOVE,
                'config' => [$name => true],
            ]);
        } else {
            $this->addDelta($class, [
                'type' => DeltaConfigCollection::CLEAR,
            ]);
        }
        return $this;
    }

    public function merge(string $class, string|null $name, array $value): static
    {
        // Check config to merge
        if ($name) {
            $config = [$name => $value];
        } else {
            $config = $value;
        }
        $this->addDelta($class, [
            'type' => DeltaConfigCollection::MERGE,
            'config' => $config,
        ]);
        return $this;
    }

    public function removeAll()
    {
        $this->deltaReset = true;
        $this->clearDeltas();
    }

    /**
     * Remove all deltas for the given class and/or key combination
     *
     * @param string $class Optional class to limit clear to
     * @param string $key Optional field to limit clear to
     */
    protected function clearDeltas($class = null, $key = null)
    {
        $this->callCache = [];

        // Clear all classes
        if (!$class) {
            $this->deltas = [];
            return;
        }

        // Clear just one class
        $classKey = strtolower($class ?? '');
        if (!$key) {
            unset($this->deltas[$classKey]);
            return;
        }

        // Filter deltas that would be ignored by this key.value replacement
        if (!isset($this->deltas[$classKey])) {
            return;
        }
        $this->deltas[$classKey] = array_filter(
            $this->deltas[$classKey] ?? [],
            function ($delta) use ($key) {
                // Clear if an array with exactly one element, with the key
                // being the affected config property
                return !isset($delta['config'])
                    || !is_array($delta['config'])
                    || (count($delta['config'] ?? []) !== 1)
                    || !isset($delta['config'][$key]);
            }
        );
    }

    /**
     * Push new delta
     *
     * @param string $class
     * @param array $delta
     */
    protected function addDelta($class, $delta)
    {
        if (is_array($delta['config'] ?? null)) {
            foreach (array_keys($delta['config']) as $configKey) {
                $this->checkForDeprecatedConfig($class, $configKey);
            }
        }
        $classKey = strtolower($class ?? '');
        if (!isset($this->deltas[$classKey])) {
            $this->deltas[$classKey] = [];
        }
        $this->deltas[$classKey][] = $delta;

        // Flush call cache
        $this->callCache = [];
    }
}
