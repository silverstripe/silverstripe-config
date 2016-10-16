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
     * @var array
     */
    protected $metadata = [];

    /**
     * @var array
     */
    protected $history = [];

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
    public function set($key, $value, $metadata = [])
    {
        if($this->trackMetadata) {
            if(isset($this->metadata[$key]) && isset($this->config[$key])) {
                if(!isset($this->history[$key])) {
                    $this->history[$key] = [];
                }

                array_unshift($this->history[$key], [
                    'value' => $this->config[$key],
                    'metadata' => $this->metadata[$key]
                ]);
            }

            $this->metadata[$key] = $metadata;
        }

        $this->config[$key] = $value;
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
    public function delete($key)
    {
        if($this->exists($key)) {
            unset($this->config[$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        if(!$this->trackMetadata || !is_array($this->metadata)) {
            return [];
        }

        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory()
    {
        if(!$this->trackMetadata || !is_array($this->history)) {
            return [];
        }

        return $this->history;
    }
}
