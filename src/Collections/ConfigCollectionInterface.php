<?php

namespace SilverStripe\Config\Collections;

use SilverStripe\Config\Middleware\Middleware;

/**
 * This represents a colletction of config keys and values.
 */
interface ConfigCollectionInterface
{
    /**
     * Fetches value for a class, or a field on that class
     *
     * @param string $class
     * @param string $name    Optional sub-key to get
     * @param mixed  $options Optional flag of middleware to disable. Passing in `true` disables
     *                        all middleware. Can also pass in int flags, or array with
     *                        `disableFlag` key with middlewares to disable
     *
     * @return mixed
     */
    public function get($class, $name = null, $options = 0);

    /**
     * Checks to see if a config item exists, or a field on that class
     *
     * @param  string         $class
     * @param  string         $name
     * @param  array|int|bool $options Optional flag of middleware to disable. Passing in `true` disables
     * all middleware. Can also pass in int flags, or array with `disableFlag` key with
     * middlewares to disable
     * @return bool
     */
    public function exists($class, $name = null, $options = 0);

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
     * which is a duplicated version of this config.
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
