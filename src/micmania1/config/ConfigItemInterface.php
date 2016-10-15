<?php

namespace micmania1\config;

interface ConfigItemInterface
{
    /**
     * Set the value and meta data
     *
     * @param mixed $value
     * @param array $metadata
     */
    public function set($value, $metadata = []);

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
    public function getMetadata();

    /**
     * Fetch the item history ordered in descending order by data
     *
     * @return CollectionItemInterface[]
     */
    public function getHistory();

    /**
     * Set whether to track history
     *
     * @param bool
     */
    public function trackMetadata($track);
}
