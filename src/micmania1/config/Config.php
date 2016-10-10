<?php

namespace micmania1\config;

use micmania1\config\MergeStrategy\NoKeyConflict;
use micmania1\config\MergeStrategy\Priority;
use micmania1\config\Transformer\TransformerInterface;

class Config implements TransformerInterface
{
    /**
     * @var array
     */
    protected $transformers = [];

    /**
     * @var array
     */
    protected $merged = [];

    /**
     * This takes a list of transformaers which are responsible for fetching and tranforming
     * their config into PHP array.
     *
     * @param TransformerInterface $transformers
     */
    public function __construct(...$transformers)
    {
        $this->transformers = $transformers;
    }

    /**
     * This loops through each transformer and trnasforms the config into the common php
     * array format.
     *
     * @return array
     */
    public function transform()
    {
        $this->merged = [];

        if (empty($this->transformers)) {
            return $this->merged;
        }

        // Each transformer returns config with sorted keys. These are then
        // all merged together into a sorted, but unmerged config.
        $unmerged = $this->transformAndSort();

        // Merge the config by sort order. Now that we have our config ordered
        // by priority we can merge the config together.
        $this->merged = $this->mergeByPriority($unmerged);

        // Return the final merged config
        return $this->merged;
    }

    /**
     * This is responsible for calling each transformer and then creating an unmerged
     * config array in blocks ordered by sort key.
     *
     * @example
     * The unmerged config looks as follows:
     * array(
     * 	10 => array(...)
     * );
     *
     * @return array
     */
    protected function transformAndSort()
    {
        $unmerged = [];

        // Run through each transformer and merge into a sorted array of configurations.
        // These will then need to be merged by priorty (lower number is higher priortiy)
        foreach ($this->transformers as $transformer) {
            $config = $transformer->transform();
            $unmerged = $this->sortConfigBlocks($config, $unmerged);
        }

        return $unmerged;
    }

    /**
     * This method takes a config block with a key which is used to sort. Its merged into
     * the existing config cleanly. If the sort key exists, then we will attempt to merge
     * that config block. If there are any key clashes at this stage, then we will throw an
     * exception.
     *
     * @example
     * $mine = array(
     * 	10 => array(...)
     * );
     *
     * $theirs = array(
     * 	5 => array(...)
     * 	15 => array(...)
     * );
     *
     * In this example, $mine would be placed in between the 5 and 15 sort keys.
     * $return = array(
     * 	5 => array(...)
     * 	10 => array(...)
     * 	15 => array(...)
     * );
     *
     * @param array $mine
     * @param array $theirs
     *
     * @return array
     */
    protected function sortConfigBlocks($mine, $theirs)
    {
        foreach ($mine as $sort => $value) {
            if (!is_int($sort)) {
                throw new Exception('Unable to sort config. Sort key must be an integer');
            }

            if (!array_key_exists($sort, $this->merged)) {
                $this->merged[$sort] = $value;
            } else {
                // If we get to this point, we have a potential key clash with the same
                // priority. If both values are an array, we can attempt to merge.
                // However, if the value is a string we cannot tell which has greater
                // priority and therefore must throw an exception. Even if the value is an
                // array, we can run into a key clash when merging those and an exception
                // will be thrown.
                $this->merged[$sort] = $this->mergeUniqueKeys($mine[$sort], $theirs[$sort]);
            }
        }

        return $this->merged;
    }

    /**
     * This will merge config blocks, but throw an exception if there is a key clash.
     *
     * @param array $mine
     * @param array $theirs
     *
     * @return array
     */
    protected function mergeUniqueKeys($mine, $theirs)
    {
        return (new NoKeyConflict)->merge($mine, $theirs);
    }

    /**
     * This will merge by priority order and overwrite any existing values that aren't arrays
     * or try to merge values that are arrays recursively.
     *
     * @param array $mine
     * @param array $theirs
     *
     * @return array
     */
    protected function mergeByPriority($mine, $theirs = [])
    {
        $merged = [];

        foreach ($mine as $sort => $block) {
            foreach ($block as $key => $value) {
                if(!is_array($value)) {
                    $merged[$key] = $value;
                    continue;
                }

                $merged[$key] = (new Priority)->merge($value, $theirs);
            }
        }

        return $merged;
    }
}
