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
     * @param string $kay
     * @param string|bool|array $value
     * @param array $metaData
     */
    public function set($key, $value, $metaData = []);

    /**
     * Get a single config key value
     *
     * @param string $key
     *
     * @return string|bool|array|null - null when no value is found
     */
    public function getValue($key);

    /**
     * Returns meta data about this key (eg. where it came from).
     *
     * @param string $key
     *
     * @todo decide what this actually returns. Structured vs array
     */
    public function getMetaData($key);

    /**
     * Fetches value and metadata for everything we have set
     *
     * @param string $key
     *
     * @todo decide return value
     */
    public function get($key);

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
    public function getKeys();

    /**
     * Returns an array of value/metadata for a key in descing order by time added
     *
     * @param string $key
     *
     * @return array
     */
    public function getHistory($ket);
}
