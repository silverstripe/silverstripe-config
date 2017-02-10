<?php

namespace SilverStripe\Config\Middleware;

trait MiddlewareAware
{
    /**
     * @var Middleware[]
     */
    protected $middlewares = [];

    /**
     * @return Middleware[]
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    /**
     * @param Middleware[] $middlewares
     * @return $this
     */
    public function setMiddlewares($middlewares)
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    /**
     * @param Middleware $middleware
     * @return $this
     */
    public function addMiddleware($middleware)
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Call middleware to get decorated class config
     *
     * @param  string   $class   Class name to pass
     * @param  mixed    $options
     * @param  callable $last    Last config to call
     * @return array Class config with middleware applied
     */
    protected function callMiddleware($class, $options, $last)
    {
        // Build middleware from back to front
        $next = $last;

        /** @var Middleware $middleware */
        foreach (array_reverse($this->getMiddlewares()) as $middleware) {
            $next = function ($class, $options) use ($middleware, $next) {
                return $middleware->getClassConfig($class, $options, $next);
            };
        }
        return $next($class, $options);
    }
}
