<?php

namespace SilverStripe\Config\Collections;

use SilverStripe\Config\Middleware\Middleware;

/**
 * This represents a collection of config keys and values.
 */
interface ConfigCollectionInterface
{
    /**
     * Fetches value for a class, or a property on that class
     *
     * @param string $class Class name to retrieve config for
     * @param string $name Optional class property to get
     * @param int|true $excludeMiddleware Optional flag of middleware to disable.
     * Passing in `true` disables all middleware.
     * Can also pass in int flags to specify specific middlewares.
     * @return mixed
     */
    public function get($class, $name = null, $excludeMiddleware = 0);

    /**
     * Checks to see if a config item exists, or a property on that class
     *
     * @param string $class Class name to check config for
     * @param string $name Optional class property to restrict check to
     * @param int|true $excludeMiddleware Optional flag of middleware to disable.
     * Passing in `true` disables all middleware.
     * Can also pass in int flags to specify specific middlewares.
     * @return bool
     */
    public function exists($class, $name = null, $excludeMiddleware = 0);

    /**
     * Returns the entire metadata
     *
     * @return array
     */
    public function getMetadata();

    /*
     * Returns the entire history
     *
     * @return array
     */
    public function getHistory();

    /**
     * Get nested version of this config,
     * which is normally duplicated version of this config,
     * but could be a subclass.
     *
     * @return static
     */
    public function nest();

    /**
     * @return Middleware[]
     */
    public function getMiddlewares();

    /**
     * @param Middleware[] $middlewares
     * @return $this
     */
    public function setMiddlewares($middlewares);

    /**
     * @param Middleware $middleware
     * @return $this
     */
    public function addMiddleware($middleware);

    /**
     * Get complete config (excludes middleware)
     *
     * @return array
     */
    public function getAll();
}
