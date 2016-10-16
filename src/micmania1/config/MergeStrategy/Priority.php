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
            if(!$theirs->exists($key)) {
                $theirs->set($key, $item['value']);
                continue;
            }

            // Get the two values for comparison
            $value = $item['value'];

            // If its an array and the key already esists, we can use array_merge
            if (is_array($value) && is_array($theirs->get($key))) {
                $value = array_merge($theirs->get($key), $value);
            }

            // The key is not set or the value is to be overwritten
            $theirs->set($key, $value, $item['metadata']);
        }

        return $theirs;
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
