<?php

namespace SilverStripe\Config\Middleware;

use Serializable;

interface Middleware extends Serializable
{
    /**
     * Get config for a class
     *
     * @param  string   $class
     * @param  mixed    $options Options flag passed in
     * @param  callable $next
     * @return string
     */
    public function getClassConfig($class, $options, $next);
}
