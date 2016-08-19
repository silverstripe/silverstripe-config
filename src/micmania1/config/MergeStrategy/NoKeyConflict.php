<?php

namespace micmania1\config\MergeStrategy;

use micmania1\config\Exceptions\KeyConflictException;

class NoKeyConflict
{
    public function merge($mine, $theirs = [])
    {
        foreach ($mine as $key => $value) {
            // If the key has not yet been set, we can set it.
            if (!array_key_exists($key, $theirs)) {
                $theirs[$key] = $value;
                continue;
            }

            // The value is the same so we can skip to the next one.
            if ($value === $theirs[$key]) {
                continue;
            }

            /*
            else if (is_int($key)) {
                // This replicates array_merge() functionality where numeric keys have their
                // value appended rather than overwritten
                $theirs[] = $value;
                continue;
            }
            */

            // If neither mine or theirs value is an array, there is a key conflict
            if (!is_array($value) || !is_array($theirs[$key])) {
                throw new KeyConflictException($key, $mine, $theirs);
            }

            // By now we've established that both values are array. We'll try to recursively
            // merge the arrays
            $theirs[$key] = $this->merge($value, $theirs[$key]);
        }

        return $theirs;
    }
}
