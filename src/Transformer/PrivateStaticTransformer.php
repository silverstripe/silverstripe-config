<?php

namespace SilverStripe\Config\Transformer;

use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use ReflectionClass;
use ReflectionProperty;

class PrivateStaticTransformer implements TransformerInterface
{
    /**
     * @var array|callable
     */
    protected $classes = null;

    /**
     * @var int
     */
    protected $sort = 0;

    /**
     * @param array|callable $classes List of classes, or callback to lazy-load
     */
    public function __construct($classes)
    {
        $this->classes = $classes;
    }

    /**
     * This loops through each class and fetches the private static config for each class.
     *
     * @param MutableConfigCollectionInterface $collection
     * @return MutableConfigCollectionInterface
     */
    public function transform(MutableConfigCollectionInterface $collection)
    {
        // Lazy-resolve class list
        $classes = $this->getClasses();

        foreach ($classes as $class) {
            // Skip if the class doesn't exist
            if (!class_exists($class)) {
                continue;
            }

            // returns an array of value and metadata
            $item = $this->getClassConfig($class);

            // Add the item to the collection
            $collection->set($class, null, $item['value'], $item['metadata']);
        }

        return $collection;
    }

    /**
     * This is responsible for introspecting a given class and returning an
     * array continaing all of its private statics
     *
     * @param  string $class
     * @return mixed
     */
    protected function getClassConfig($class)
    {
        $reflection = new ReflectionClass($class);

        /** @var ReflectionProperty[] **/
        $props = $reflection->getProperties(ReflectionProperty::IS_STATIC);

        $classConfig = [];
        foreach ($props as $prop) {
            if (!$prop->isPrivate()) {
                // Ignore anything which isn't private
                continue;
            }

            $prop->setAccessible(true);
            $classConfig[$prop->getName()] = $prop->getValue();
        }

        // Create the metadata for our new item
        $metadata = [
            'filename' => $reflection->getFileName(),
            'class' => $class,
            'transformer' => static::class
        ];

        return ['value' => $classConfig, 'metadata' => $metadata];
    }

    /**
     * @return array
     */
    public function getClasses()
    {
        if (!is_array($this->classes) && is_callable($this->classes)) {
            return call_user_func($this->classes);
        }

        return $this->classes;
    }
}
