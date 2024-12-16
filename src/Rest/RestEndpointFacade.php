<?php

namespace CoderPress\Rest;

use Psr\SimpleCache\CacheInterface;

class RestEndpointFacade
{
    /**
     * Creates a new instance of the AbstractRestEndpoint class and registers it.
     *
     * @param string $namespace The namespace for the endpoint.
     * @param string $route The route for the endpoint.
     * @param array $args The arguments accepted by the endpoint.
     * @param array $methods The HTTP methods allowed for the endpoint.
     * @param callable[] $middlewares An array of middleware callbacks.
     * @param bool $cached Whether middlewares include a caching process or not.
     * @param int $cacheSerializingMethod In case a caching process is up, which serialization method to be used.
     * @param \Datetime|int $cacheExpires Expire time for caching in seconds or as a \DateTime object
     * 
     * @return AbstractRestEndpoint The created endpoint instance.
     */
    public static function createEndpoint(
                    string $namespace,
                    string $route,
                    \Closure $callback,
                    $permissionCallback = false,
                    array $args = [],
                    array $methods = ['GET'],
                    array $middlewares = [],
                    ?CacheInterface $cache = null,
                    int $cacheSerializingMethod = 0,
                    \DateTime|int $cacheExpires = 3600): AbstractRestEndpoint
    {
        if (!$permissionCallback) {
            $permissionCallback = function () { return true; };
        }

        $endpoint = new class($namespace, $route, $callback, $permissionCallback, $args, $methods, $middlewares, $cache, $cacheSerializingMethod, $cacheExpires) extends AbstractRestEndpoint
        {            
            public function __construct (
                                $namespace, 
                                $route, 
                                $callback, 
                                $permissionCallback, 
                                $args, 
                                $methods, 
                                $middlewares, 
                                $cache, 
                                $cacheSerializingMethod,
                                $cacheExpires) {
                
                parent::__construct(
                        $namespace, 
                        $route, 
                        $callback, 
                        $permissionCallback, 
                        $args, 
                        $methods, 
                        $middlewares,
                        $cache, 
                        $cacheSerializingMethod,
                        $cacheExpires
                    );
            }
        };
        
        $endpoint->register();
        return $endpoint;
    }
}
