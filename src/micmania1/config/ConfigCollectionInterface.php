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
     * @param ConfigItemInterface $item
     */
    public function set($key, $item);

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
    public function clear($key);

    /**
     * Returns an array of available config keys
     *
     * @return string[]
     */
    public function keys();

    /**
     * Fetches all config items
     *
     * @return array
     */
    public function all();
}
