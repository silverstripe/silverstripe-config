<?php

namespace SilverStripe\Config\Collections;

interface MutableConfigCollectionInterface extends ConfigCollectionInterface
{
    /**
     * Sets config for a given field.
     * Set name to null to set the config for the entire class.
     *
     * @param  string $class
     * @param  string $name
     * @param  mixed  $value
     * @param  array  $metadata
     * @return $this
     */
    public function set($class, $name, $value, $metadata = []);

    /**
     * Merge a config for a class, or a field on that class
     *
     * @param  string $class
     * @param  string $name
     * @param  mixed  $value
     * @return $this
     */
    public function merge($class, $name, $value);

    /**
     * Remove config for a given class, or field on that class
     *
     * @param  string $class
     * @param  string $name
     * @return $this
     */
    public function remove($class, $name = null);

    /**
     * Delete all entries
     */
    public function removeAll();
}
