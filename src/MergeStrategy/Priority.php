<?php

namespace SilverStripe\Config\MergeStrategy;

use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

class Priority
{
    /**
     * Merges an array of values into a collection
     *
     * @param array $mine Map of key to array with value and metadata sub-keys
     * @param MutableConfigCollectionInterface $theirs
     * @return MutableConfigCollectionInterface
     */
    public static function merge(array $mine, MutableConfigCollectionInterface $theirs)
    {
        foreach ($mine as $class => $item) {
            // Ensure we have value/metadata keys
            $item = static::normaliseItem($item);
            $value = $item['value'];
            $metadata = $item['metadata'];

            // If the item doesn't exist in theirs, we can just set it and continue.
            $theirValue = $theirs->get($class, null, true);
            if (!isset($theirValue)) {
                $theirs->set($class, null, $value, $metadata);
                continue;
            }

            // If its an array and the key already esists, we can use array_merge
            if (is_array($value) && is_array($theirValue)) {
                $value = static::mergeArray($value, $theirValue);
            }

            // Preserve metadata
            if (!$metadata) {
                $theirMetadata = $theirs->getMetadata();
                if (isset($theirMetadata[$class])) {
                    $metadata = $theirMetadata[$class];
                }
            }
            $theirs->set($class, null, $value, $metadata);
        }

        return $theirs;
    }

    /**
     * Deep merges a high priorty array into a lower priority array, overwriting duplicate
     * keys. If the keys are integers, then the merges acts like array_merge() and adds a new
     * item.
     *
     * @param array $highPriority
     * @param array $lowPriority
     *
     * @return array
     */
    public static function mergeArray(array $highPriority, array $lowPriority)
    {
        foreach ($highPriority as $key => $value) {
            // If value isn't an array, we can overwrite whatever was before it
            if (!is_array($value)) {
                if (is_int($key)) {
                    $lowPriority[] = $value;
                } else {
                    $lowPriority[$key] = $value;
                }

                continue;
            }

            // If not set, or we're changing type we can set low priority
            if (is_int($key) || !array_key_exists($key, $lowPriority ?? []) || !is_array($lowPriority[$key])) {
                if (is_int($key)) {
                    $lowPriority[] = $value;
                } else {
                    $lowPriority[$key] = $value;
                }

                continue;
            }

            // We have two arrays, so we merge
            $lowPriority[$key] = static::mergeArray($value, $lowPriority[$key]);
        }

        return $lowPriority;
    }

    /**
     * Returns a normalised array with value/metadata keys
     *
     * @param array
     *
     * @return array
     */
    protected static function normaliseItem(array $item)
    {
        if (!isset($item['value'])) {
            $item['value'] = '';
        }

        if (!isset($item['metadata'])) {
            $item['metadata'] = [];
        }

        return ['value' => $item['value'], 'metadata' => $item['metadata']];
    }
}
