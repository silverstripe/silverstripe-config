<?php

namespace micmania1\config;

interface ConfigItemInterface
{
    /**
     * Set the value and meta data
     *
     * @param mixed $value
     * @param array $metaData
     */
    public function set($value, $metaData = []);

    /**
     * Get the value of a config item
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Get the value of a config item
     *
     * @return array
     */
    public function getMetaData();

    /**
     * Fetch the item history ordered in descending order by data
     *
     * @return CollectionItemInterface[]
     */
    public function getHistory();
}
