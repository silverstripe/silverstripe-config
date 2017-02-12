<?php

namespace micmania1\config\MergeStrategy;

use micmania1\config\ConfigCollectionInterface;
use micmania1\config\ConfigCollection;

class Priority
{
    public function merge(array $mine, ConfigCollectionInterface $theirs) {
        foreach ($mine as $key => $item) {

            // Ensure we have value/metadata keys
            $item = $this->normaliseItem($item);

            // If the item doesn't exist in theirs, we can just set it and continue.
            $theirValue = $theirs->get($key);
            if(is_null($theirValue)) {
                $theirs->set($key, $item['value']);
                continue;
            }

            // Get the two values for comparison
            $value = $item['value'];

            // If its an array and the key already esists, we can use array_merge
            if (is_array($value) && is_array($theirValue)) {
                $value = $this->mergeArray($value, $theirValue);
            }

            // The key is not set or the value is to be overwritten
            $theirs->set($key, $value, $item['metadata']);
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
    public function mergeArray(array $highPriority, array $lowPriority)
    {
        foreach($highPriority as $key => $value) {
            // If value isn't an array, we can overwrite whatever was before it
            if(!is_array($value)) {
                if(is_int($key)) {
                    $lowPriority[] = $value;
                } else {
                    $lowPriority[$key] = $value;
                }

                continue;
            }

            // If not set, or we're changing type we can set low priority
            if(!isset($lowPriority[$key]) || !is_array($lowPriority[$key])) {
                if(is_int($key)) {
                    $lowPriority[] = $value;
                } else {
                    $lowPriority[$key] = $value;
                }

                continue;
            }

            // We have two arrays, so we merge
            $lowPriority[$key] = $this->mergeArray($value, $lowPriority[$key]);
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
    protected function normaliseItem(array $item)
    {
        if(!isset($item['value'])) {
            $item['value'] = '';
        }

        if(!isset($item['metadata'])) {
            $item['metadata'] = [];
        }

        return ['value' => $item['value'], 'metadata' => $item['metadata']];
    }
}
