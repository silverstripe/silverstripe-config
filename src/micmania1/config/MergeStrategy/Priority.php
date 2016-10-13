<?php

namespace micmania1\config\MergeStrategy;

class Priority
{
    public function merge($mine, ConfigCollectionInterface $collection)
    {
        foreach ($mine as $key => $value) {
            // Get the existing value
            $existingValue = $collection->getValue($key);

            // If its an array and the key already esists, we can use array_merge
            if (is_array($value) && $existingValue) {
                if(!is_array($existingValue)) {
                    $existingValue = [];
                }

                $value = array_merge($existingValue, $value);
            }

            // The key is not set or the value is to be overwritten
            $collection->set($key, $value);
        }

        return $theirs;
    }
}
