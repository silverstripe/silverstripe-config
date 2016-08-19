<?php

namespace micmania1\config\MergeStrategy;

class Priority
{
    public function merge($mine, $theirs = [])
    {
        foreach ($mine as $key => $value) {
            // If its an array and the key already esists, we can use array_merge
            if (is_array($value) && array_key_exists($key, $theirs)) {
                $theirs[$key] = array_merge($theirs[$key], $value);

                continue;
            }

            // The key is not set or the value is to be overwritten
            $theirs[$key] = $value;
        }

        return $theirs;
    }
}
