<?php

namespace micmania1\config\MergeStrategy;

use micmania1\config\ConfigCollectionInterface;
use micmania1\config\ConfigCollection;

class Priority
{
    public function merge(array $mine, ConfigCollectionInterface $theirs) {
        foreach ($mine as $key => $item) {
            if(!isset($item['value'])) {
                continue;
            }

            if(!isset($item['metadata'])) {
                $item['metadata'] = [];
            }

            // If the item doesn't exist in theirs, we can just set it and continue.
            if(!$theirs->exists($key)) {
                $theirs->set($key, $item['value']);
                continue;
            }

            /** @var ConfigItemInterface **/
            $theirsValue = $theirs->get($key);

            // Get the two values for comparison
            $lessImportantValue = $theirsValue;
            $newValue = $item['value'];

            // If its an array and the key already esists, we can use array_merge
            if (is_array($newValue) && is_array($lessImportantValue)) {
                $newValue = array_merge($lessImportantValue, $newValue);
            }

            // The key is not set or the value is to be overwritten
            $theirs->set($key, $newValue, $item['metadata']);
        }

        return $theirs;
    }
}
