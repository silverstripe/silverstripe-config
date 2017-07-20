<?php

namespace SilverStripe\Config\Transformer;

use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use ReflectionClass;
use ReflectionProperty;
use LogicException;

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

        /** @var ReflectionProperty[] $props **/
        $props = $reflection->getProperties(ReflectionProperty::IS_STATIC);

        $classConfig = [];
        foreach ($props as $prop) {
            // Check if this property is configurable
            if (!$this->isConfigProperty($prop)) {
                continue;
            }

            // Note that some non-config private statics may be assigned
            // un-serializable values. Detect these here
            $prop->setAccessible(true);
            $value = $prop->getValue();
            if ($this->isConfigValue($value)) {
                $classConfig[$prop->getName()] = $value;
            }
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
     * Is a var config or not?
     *
     * @param ReflectionProperty $prop
     * @return bool
     */
    protected function isConfigProperty(ReflectionProperty $prop)
    {
        if (!$prop->isPrivate()) {
            // If this non-private static overrides any private configs, make this an error
            $class = $prop->getDeclaringClass();
            while ($class = $class->getParentClass()) {
                if (!$class->hasProperty($prop->getName())) {
                    continue;
                }
                $parentProp = $class->getProperty($prop->getName());
                if (!$parentProp->isPrivate()) {
                    continue;
                }
                throw new LogicException(
                    $prop->getDeclaringClass()->getName().'::'.$prop->getName()
                    . ' is not private but overrides a private static config in parent class '
                    . $class->getName()
                );
            }
            return false;
        }
        $annotations = $prop->getDocComment();
        // Whitelist @config
        if (strstr($annotations, '@config')) {
            return true;
        }
        // Don't treat @internal as config
        if (strstr($annotations, '@internal')) {
            return false;
        }
        return true;
    }

    /**
     * Detect if a value is a valid config
     *
     * @param mixed $input
     * @return true
     */
    protected function isConfigValue($input)
    {
        if (is_object($input) || is_resource($input)) {
            return false;
        }
        if (is_array($input)) {
            foreach ($input as $next) {
                if (!$this->isConfigValue($next)) {
                    return false;
                }
            }
        }
        return true;
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
