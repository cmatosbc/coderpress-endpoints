<?php

namespace CoderPress\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Psr\SimpleCache\CacheInterface;
use CoderPress\Utilities\Time;

abstract class AbstractRestEndpoint
{
    const SERIALIZE_PHP = 0;
    const SERIALIZE_JSON = 1;
    const SERIALIZE_IGBINARY = 2;

    /**
     * Constructor for the AbstractRestEndpoint class.
     *
     * @param string $namespace The namespace for the endpoint.
     * @param string $route The route for the endpoint.
     * @param array $args The arguments accepted by the endpoint.
     * @param array $methods The HTTP methods allowed for the endpoint.
     * @param callable[] $middlewares An array of middleware callbacks.
     * @param bool $cached Whether middlewares include a caching process or not.
     * @param int $cacheSerializingMethod In case a caching process is up, which serialization method to be used.
     * @param \Datetime|int $cacheExpires Expire time for caching in seconds or as a \DateTime object to be compared to the current time
     * 
     * @author Carlos Matos | Team Zenith
     * 
     */
    public function __construct(
                    protected string $namespace,
                    protected string $route,
                    public \Closure $callback,
                    public \Closure $permissionCallback,
                    protected array $args = [],
                    protected array $methods = ['GET'],
                    protected array $middlewares = [],
                    protected ?CacheInterface $cache = null,
                    protected $cacheSerializingMethod = self::SERIALIZE_PHP,
                    protected \DateTime|int $cacheExpires = 3600)
    {
        if ($cacheExpires instanceof \DateTime) {
            $this->cacheExpires = Time::getTimeDiff($cacheExpires);
        }
    }

    /**
     * Adds the cache middleware to the endpoint if a cache instance is provided.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|null The response object if the request is cached, or null otherwise.
     */
    private function addCacheProcess(\WP_REST_Request $request) {
        $cache = $this->cache;
        $key = md5(implode('.', $request->get_params()));
        $isCached = $cache->get($key);

        if ($isCached) {
            return new WP_REST_Response($this->selectiveUnserialize($isCached), 200);
        }
    }

    /**
     * Registers the endpoint with the WordPress REST API.
     */
    public function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route($this->namespace, $this->route, [
                'methods' => $this->methods,
                'callback' => [$this, 'handle'],
                'permission_callback' => $this->permissionCallback,
                'args' => $this->args,
            ]);
        });
    }

    /**
     * Adds a middleware callback to the endpoint.
     *
     * @param callable $middleware The middleware callback.
     */
    public function addMiddleware(callable $middleware)
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Handles the endpoint request.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $response = $process = null;

        if (!is_null($this->cache)) {
            $response = $this->addCacheProcess($request);
        }

        foreach ($this->middlewares as $middleware) {
            $process = $middleware($request);

            if ($process instanceof WP_REST_Response) {
                return $process;
            }
        }

        if (is_null($response)) {
            $callable = $this->callback;
            $response = $callable($request);
        }

        if (is_array($response)) {
            if (!is_null($this->cache)) {
                $key = md5(implode('.', $request->get_params()));
                $this->cache->set($key, $this->selectiveSerialize($response), $this->cacheExpires);
            }
            return $this->getResponse($response);
        }

        return $response;
    }

    /**
     * Selectively serializes data based on the configured serialization method.
     *
     * @param mixed $data The data to serialize.
     * @return string The serialized data.
     */
    private function selectiveSerialize(mixed $data): string
    {
        return match ($this->cacheSerializingMethod) {
            0 => serialize($data),
            1 => json_encode($data),
            2 => igbinary_serialize($data),
            default => $data,
        };
    }

    /**
     * Selectively unserializes data based on the configured serialization method.
     *
     * @param mixed $data The serialized data.
     * @return mixed The unserialized data.
     */
    private function selectiveUnserialize(mixed $data): mixed
    {
        return match ($this->cacheSerializingMethod) {
            0 => unserialize($data),
            1 => json_decode($data, true),
            2 => igbinary_unserialize($data),
            default => $data,
        };
    }

    /**
     * Creates a new WP_REST_Response object.
     *
     * @param array $data The data to include in the response.
     * @param int $status The HTTP status code for the response.
     * @return WP_REST_Response The response object.
     */
    protected function getResponse(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response($data, $status);
    }
}
