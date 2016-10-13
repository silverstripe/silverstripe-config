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

    public function __construct($trackMetaData = true, $trackHistory = true)
    {
        $this->trackMetaData = (bool) $trackMetaData;
        $this->trackHistory = (bool) $trackHistory;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $metaData = [])
    {
        if(!$this->hasKey($key)) {

            // Set a new key
            $this->config[$key] = [];

            // Setup the defaults, even if metadata/history aren't being used
            $this->config[$key]['metadata'] = [];
            $this->config[$key]['history'] = [];

        } else if ($this->trackHistory) {

            // if we're tracking history, we keep a record of the value and metadata
            $history = [
                'value' => $this->getValue($key),
                'metadata' => $this->getMetaData($key)
            ];

            $this->config[$key]['history'][] = $history;
        }

        // Overwrite the value for new and existing keys
        $this->config[$key]['value'] = $value;

        // If we're tracking meta data, update the meta data for new and existing keys
        if($this->trackMetaData) {
            $this->config[$key]['metadata'] = $metaData;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($key)
    {
        if(!$this->hasKey($key)) {
            return null;
        }

        return $this->config[$key]['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetaData($key)
    {
        if(!$this->hasKey($key)) {
            return null;
        }

        return $this->config[$key]['metadata'];
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if(!$this->hasKey($key)) {
            return null;
        }

        return $this->config[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function clear($key)
    {
        if($this->hasKey($key)) {
            unset($this->config[$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        return array_keys($this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function hasKey($key)
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory($key)
    {
        if(!$this->hasKey($key)) {
            return [];
        }

        return $this->config[$key]['history'];
    }
}
