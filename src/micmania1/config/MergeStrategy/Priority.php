<?php

namespace micmania1\config\MergeStrategy;

use micmania1\config\ConfigCollectionInterface;
use micmania1\config\ConfigCollection;

class Priority
{
    public function merge(ConfigCollectionInterface $mine, ConfigCollectionInterface $theirs) {
        foreach ($mine->all() as $key => $item) {
            // If the item doesn't exist in theirs, we can just set it and continue.
            if(!$theirs->exists($key)) {
                $theirs->set($key, $item);
                continue;
            }

            /** @var ConfigItemInterface **/
            $theirsItem = $theirs->get($key);

            // Get the two values for comparison
            $lessImportantValue = $theirsItem->getValue();
            $importantValue = $item->getValue();

            // If its an array and the key already esists, we can use array_merge
            if (is_array($importantValue) && is_array($lessImportantValue)) {
                $importantValue = array_merge($lessImportantValue, $importantValue);
            }

            // The key is not set or the value is to be overwritten
            $theirsItem->set($importantValue, $item->getMetaData());
        }

        return $theirs;
    }
}
