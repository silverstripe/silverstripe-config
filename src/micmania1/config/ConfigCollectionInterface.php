<?php

namespace micmania1\config;

/**
 * This represents a colletction of config keys and values.
 */
interface ConfigCollectionInterface
{
    /**
     * Set the value of a single item
     *
     * @param string $key
     * @param mixed $value
     * @param array $metadata
     */
    public function set($key, $value, $metadata = []);

    /**
     * Fetches value and metadata for everything we have set
     *
     * @param string $key
     *
     * @return ConfigItemInterface|null
     */
    public function get($key);

    /**
     * Checks to see if a config item exists
     *
     * @param string $key
     *
     * @return boolean
     */
    public function exists($key);

    /**
     * Removed a config item including any associated metadata
     *
     * @param string $key
     */
    public function delete($key);

    /**
     * Returns the entire metadata
     *
     * @return array
     */
    public function getMetadata();

    /*
     * Returns the entire history
     *
     * @return array
     */
    public function getHistory();
}
