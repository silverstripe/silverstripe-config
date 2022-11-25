<?php

namespace SilverStripe\Config\Middleware;

interface Middleware
{
    /**
     * Get config for a class
     *
     * @param string $class Name of class
     * @param int|true $excludeMiddleware Middleware disable flags
     * @param callable $next Callback to next middleware
     * @return array Complete class config
     */
    public function getClassConfig($class, $excludeMiddleware, $next);
}
