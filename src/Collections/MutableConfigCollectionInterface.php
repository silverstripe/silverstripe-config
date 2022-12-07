<?php

namespace SilverStripe\Config\Collections;

interface MutableConfigCollectionInterface extends ConfigCollectionInterface
{
    /**
     * Sets config for a given field.
     * Set name to null to set the config for the entire class.
     */
    public function set(string $class, ?string $name, mixed $value, array $metadata = []): static;

    /**
     * Merge a config for a class, or a field on that class
     */
    public function merge(string $class, string $name, array $value): static;

    /**
     * Remove config for a given class, or field on that class
     */
    public function remove(string $class, ?string $name = null): static;

    /**
     * Delete all entries
     */
    public function removeAll();
}
