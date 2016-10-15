<?php

namespace micmania1\config\MergeStrategy;

use micmania1\config\Exceptions\KeyConflictException;
use micmania1\config\ConfigCollectionInterface;
use micmania1\config\ConfigCollection;
use Exception;

class NoKeyConflict
{
    public function merge(ConfigCollectionInterface $mine, ConfigCollectionInterface $theirs)
    {
        foreach ($mine->all() as $key => $item) {

            // The item doesn exist in theirs, so we can safely set it.
            if(!$theirs->exists($key)) {
                $theirs->set($key, $item);
                continue;
            }

            $myValue = $item->getValue();
            $theirsItem = $theirs->get($key);
            $theirsValue = $theirsItem->getValue();

            // If two items are the same, we stil overwrite as to keep a record
            // of the hsitory if this is being tracked.
            if($myValue === $theirsValue) {
                $theirs->set($key, $item);
                continue;
            }

            // If the two items aren't an array and they exist then we have a key conflict
            if(!is_array($myValue) || !is_array($theirsValue)) {
                throw new KeyConflictException($key, $mine, $theirs);
            }

            // By now we know that both items are arrays - we need to merge them together
            // without key conflicts
            try {
                $value = $this->mergeArrays($myValue, $theirsValue);
            } catch (Exception $e) {
                // The array has a key conflict
                throw new KeyConflictException($key, $mine, $theirs);
            }

            // The item already exists in theirs, so we just update that.
            $theirsItem->set($value);
        }

        return $theirs;
    }

    /**
     * This (almost) mimics the functionality as above, but works with arrays instead of
     * collections.
     *
     * @param array $mine
     * @param array $theirs
     *
     * @return array
     */
    protected function mergeArrays($mine, $theirs)
    {
        foreach($mine as $key => $value) {
            // If they key doesn't exist in theirs, we can safely set it
            if(!isset($theirs[$key])) {
                $theirs[$key] = $value;
                continue;
            }

            // The value is the same so we can skip to the next one.
            if ($mine[$key] === $theirs[$key]) {
                continue;
            }

            // If the values aren't arrays, then we have a key conflict
            if(!is_array($value) || !is_array($theirs[$key])) {
                throw new Exception("There is a key conflict in array.");
            }

            $theirs[$key] = $this->mergeArrays($value, $theirs[$key]);
        }

        return $theirs;
    }
}
