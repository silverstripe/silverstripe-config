<?php

namespace micmania1\config\Transformer;

use micmania1\config\ConfigCollectionInterface;
use ReflectionClass;
use ReflectionProperty;

class PrivateStaticTransformer implements TransformerInterface
{
    /**
     * @var array
     */
    protected $classes = [];

    /**
     * @var int
     */
    protected $sort = 0;

    /**
     * @var ConfigCollectionInterface
     */
    protected $collection;

    /**
     * @param array $classes
     */
    public function __construct(array $classes, ConfigCollectionInterface $collection)
    {
        $this->classes = $classes;
        $this->collection = $collection;
    }

    /**
     * This loops through each class and fetches the private static config for each class.
     */
    public function transform()
    {
        foreach($this->classes as $class) {
            // Skip if the class doesn't exist
            if(!class_exists($class)) {
                continue;
            }

            // returns an array of value and metadata
            $item = $this->getClassConfig($class);

            // Add the item to the collection
            $this->collection->set($class, $item['value'], $item['metadata']);
        }

        return $this->collection;
    }

    /**
     * This is responsible for introspecting a given class and returning an
     * array continaing all of its private statics
     *
     * @param string $class
     *
     * @return mixed
     */
    protected function getClassConfig($class)
    {
        $reflection = new ReflectionClass($class);

        /** @var ReflectionProperty[] **/
        $props = $reflection->getProperties(ReflectionProperty::IS_STATIC);

        $classConfig = [];
        foreach($props as $prop) {
            if(!$prop->isPrivate()) {
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

}
