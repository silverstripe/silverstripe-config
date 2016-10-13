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
     * {@inheritdoc}
     */
    public function set($key, ConfigItemInterface $item)
    {
        if(!$this->exists($key)) {
            $this->config[$key] = $item;
        }

        $existing = $this->config[$key];
        $existing->set($item->getValue(), $item->getMetaData());
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
