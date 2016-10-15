<?php

namespace micmania1\config;

class ConfigItem implements ConfigItemInterface
{
    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var array
     */
    protected $metaData = [];

    /**
     * @var array
     */
    protected $history = [];

    /**
     * @var boolean
     */
    protected $trackMetadata = false ;

    public function __construct($value, array $metaData = [], $trackMetadata = false)
    {
        $this->value = $value;
        $this->trackMetadata = (boolean) $trackMetadata;
        if($this->trackMetadata) {
            $this->metaData = $metaData;
        }
    }

    public function set($value, $metaData = [])
    {
        if($this->trackMetadata) {
            // Clone will clear the history to prevent recursion
            $previous = clone $this;
            array_unshift($this->history, $previous);
            $this->metaData = $metaData;
        }

        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metaData;
    }

    /**
     * Fetch the item history ordered in descending order by data
     *
     * @return CollectionItemInterface[]
     */
    public function getHistory()
    {
        return $this->history;
    }

    public function trackMetadata($track)
    {
        $this->trackMetadata = $track;
    }

    /**
     * History is an array of historical records of itself so we clear it to prevent
     * recursion.
     */
    public function __clone()
    {
        $this->history = [];
    }
}
