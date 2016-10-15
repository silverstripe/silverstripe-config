<?php

namespace micmania1\config;

class ConfigCollection implements ConfigCollectionInterface
{

    /**
     * Stores a list of key/value config.
     *
     * @var array
     */
    protected $config = [];

    /**
     * @var boolean
     */
    protected $trackMetadata = false;

    public function __construct($trackMetadata = false)
    {
        $this->trackMetadata = $trackMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $item)
    {
        if(!$this->exists($key)) {
            $this->config[$key] = $item;
        }

        // Get the existing item so we can set the new value on it
        $existing = $this->config[$key];

        // Ensure that that tracking is correct for items belonging to this collection
        $existing->trackMetadata($this->trackMetadata);

        $existing->set($item->getValue(), $item->getMetadata());
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if(!$this->exists($key)) {
            return null;
        }

        return $this->config[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($key)
    {
        if($this->exists($key)) {
            unset($this->config[$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->config;
    }
}
