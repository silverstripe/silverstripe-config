# Middleware

Middleware is a config transformer which may be applied at run time over
the config for a single class.

## Getting config

Middleware are applied in order of creation during ->get() or ->exists() 
calls to config, each able to extend or modify config generated from
prior middleware.
 
When requesting config, individual middleware may be disabled via
the $excludeMiddleware flag, which is an int value (which may be assigned
multiple int flags via bitwise `|`) which is passed as the second argument
to the middlewares `getClassConfig` method.

`true` may be passed in as a global disable for all middleware.

## Modifying config

Mutable methods such as ->set(), ->remove() or ->merge() always apply to the
root config prior to middleware being applied.

## Example middleware

Middleware could be used to implement common design patters such as
config inheritance, caching, or decorators.

E.g. this is a middleware which adds extra data to class config

    :::php
    class ConfigExtender implements Middleware
    {
        public function getClassConfig($class, $excludeMiddleware, $next)
        {
            // Get config from next middleware
            $config = $next($class, $excludeMiddleware);
    
            // Middlewares are responsible for self-disabling
            if ($excludeMiddleware === true || ($excludeMiddleware & self::DISABLE_FLAG)) {
                return $config;
            }
    
            // Merge config with extra data
            return array_merge($config, $this->getExtraConfig($class));
        }
    }

