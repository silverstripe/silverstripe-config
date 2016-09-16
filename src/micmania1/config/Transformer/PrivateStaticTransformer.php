<?php

namespace micmania1\config\Transformer;

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
     * @param array $classes
     */
    public function __construct(array $classes, $sort = 0)
    {
        $this->classes = $classes;
        $this->sort = $sort;
    }

    /**
     * This loops through each class and fetches the private static config for each class.
     */
    public function transform()
    {
        $config = [];
        foreach($this->classes as $class) {
            // Skip if the class doesn't exist
            if(!class_exists($class)) {
                continue;
            }

            $config[$class] = $this->getClassConfig($class);
        }

        return [$this->sort => $config];
    }

    /**
     * This is responsible for introspecting a given class and returning an
     * array continaing all of its private statics
     *
     * @param string $class
     *
     * @return string[]
     */
    protected function getClassConfig($class)
    {
        /** @var \ReflectionProperty[] **/
        $props = (new ReflectionClass($class))
            ->getProperties(ReflectionProperty::IS_STATIC);

        $classConfig = [];

        foreach($props as $prop) {
            if(!$prop->isPrivate()) {
                // Ignore anything which isn't private
                continue;
            }

            $prop->setAccessible(true);
            $classConfig[$prop->getName()] = $prop->getValue();
        }

        return $classConfig;
    }

}
