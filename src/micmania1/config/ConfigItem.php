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
    protected $trackHistory = true;

    public function __construct($value, array $metaData = [], $trackHistory = true)
    {
        $this->value = $value;
        $this->metaData = $metaData;
        $this->trackHistory = (boolean) $trackHistory;
    }

    public function set($value, $metaData = [])
    {
        if($this->trackHistory) {
            // Clone will clear the history to prevent recursion
            $previous = clone $this;
            $this->history[] = $previous;
        }

        $this->value = $value;
        $this->metaData = $metaData;
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
    public function getMetaData()
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

    /**
     * History is an array of historical records of itself so we clear it to prevent
     * recursion.
     */
    public function __clone()
    {
        $this->history = [];
    }
}
