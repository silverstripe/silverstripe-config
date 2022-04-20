<?php

namespace SilverStripe\Config\Middleware;

use InvalidArgumentException;
use SilverStripe\Config\Collections\DeltaConfigCollection;
use SilverStripe\Config\MergeStrategy\Priority;

/**
 * Applies a set of user-customised modifications to config
 */
class DeltaMiddleware implements Middleware
{
    use MiddlewareCommon;

    /**
     * Source for deltas
     *
     * @var DeltaConfigCollection
     */
    protected $collection = null;

    /**
     * DeltaMiddleware constructor.
     *
     * @param DeltaConfigCollection $collection
     * @param int $disableFlag
     */
    public function __construct(DeltaConfigCollection $collection, $disableFlag = 0)
    {
        $this->setCollection($collection);
        $this->setDisableFlag($disableFlag);
    }

    /**
     * @return DeltaConfigCollection
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param DeltaConfigCollection $collection
     * @return $this
     */
    public function setCollection(DeltaConfigCollection $collection)
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * Get config for a class
     *
     * @param string $class Name of class
     * @param int|true $excludeMiddleware Middleware disable flags
     * @param callable $next Callback to next middleware
     * @return array Complete class config
     */
    public function getClassConfig($class, $excludeMiddleware, $next)
    {
        // Check if enabled
        $enabled = $this->enabled($excludeMiddleware);
        if (!$enabled) {
            return $next($class, $excludeMiddleware);
        }

        // Note: If config is reset, we don't need to call parent config
        if ($this->getCollection()->isDeltaReset($class)) {
            $config = [];
        } else {
            $config = $next($class, $excludeMiddleware);
        }

        // Apply all deltas
        $deltas = $this->getCollection()->getDeltas($class);
        foreach ($deltas as $delta) {
            $config = $this->applyDelta($config, $delta);
        }
        return $config;
    }

    /**
     * Apply a single delta to a class config
     *
     * @param array $config
     * @param array $delta
     * @return array
     */
    protected function applyDelta($config, $delta)
    {
        switch ($delta['type']) {
            case DeltaConfigCollection::SET:
                return array_merge($config, $delta['config']);
            case DeltaConfigCollection::MERGE:
                return Priority::mergeArray($delta['config'], $config);
            case DeltaConfigCollection::CLEAR:
                return [];
            case DeltaConfigCollection::REPLACE:
                return $delta['config'];
            case DeltaConfigCollection::REMOVE:
                return array_diff_key($config ?? [], $delta['config']);
            default:
                throw new InvalidArgumentException("Invalid delta " . $delta['type']);
        }
    }
}
