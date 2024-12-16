<?php

namespace CoderPress\Rest\Middleware;

use WP_REST_Request;
use WP_REST_Response;

/**
 * CORS (Cross-Origin Resource Sharing) Middleware
 * 
 * Handles CORS preflight requests and adds appropriate CORS headers to responses.
 * Supports configurable origins, methods, headers, and preflight caching.
 * 
 * @package CoderPress\Rest\Middleware
 * @author Carlos Matos | Team CoderPress
 * @since 1.0.0
 */
class CorsMiddleware
{
    /**
     * @var array List of allowed origins. Use ['*'] to allow all origins.
     */
    private array $allowedOrigins;

    /**
     * @var array List of allowed HTTP methods
     */
    private array $allowedMethods;

    /**
     * @var array List of allowed headers
     */
    private array $allowedHeaders;

    /**
     * @var int Cache duration for preflight requests in seconds
     */
    private int $maxAge;

    /**
     * Constructor for the CorsMiddleware
     * 
     * @param array $allowedOrigins List of allowed origins. Default ['*'] allows all origins.
     * @param array $allowedMethods List of allowed HTTP methods. Default includes common methods.
     * @param array $allowedHeaders List of allowed headers. Default includes common headers.
     * @param int $maxAge Duration in seconds to cache preflight requests. Default 1 hour.
     */
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization'],
        int $maxAge = 3600
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->maxAge = $maxAge;
    }

    /**
     * Middleware invocation handler
     * 
     * Processes the request and adds appropriate CORS headers to the response.
     * Handles preflight (OPTIONS) requests automatically.
     * 
     * @param WP_REST_Request $request The incoming request object
     * @param callable $next The next middleware in the chain
     * @return WP_REST_Response The modified response with CORS headers
     */
    public function __invoke(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        $response = $next($request);
        
        $origin = $request->get_header('origin');
        
        if ($origin && ($this->allowedOrigins === ['*'] || in_array($origin, $this->allowedOrigins))) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }

        if ($request->get_method() === 'OPTIONS') {
            $response->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
            $response->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
            $response->header('Access-Control-Max-Age', $this->maxAge);
        }

        $response->header('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
}
