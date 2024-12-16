<?php

namespace CoderPress\Rest\Middleware;

/**
 * Factory class for creating middleware instances
 * 
 * Provides static methods to create configured middleware instances
 * for common API requirements such as CORS and request sanitization.
 * 
 * @package CoderPress\Rest\Middleware
 * @author Carlos Matos | Team CoderPress
 * @since 1.0.0
 */
class MiddlewareFactory
{
    /**
     * Creates a CORS middleware instance
     * 
     * Configures Cross-Origin Resource Sharing (CORS) headers and
     * handles preflight requests for REST API endpoints.
     * 
     * @param array $allowedOrigins List of allowed origins. Default ['*'] allows all origins.
     * @param array $allowedMethods List of allowed HTTP methods. Default includes common methods.
     * @param array $allowedHeaders List of allowed headers. Default includes common headers.
     * @param int $maxAge Duration in seconds to cache preflight requests. Default 1 hour.
     * @return callable The configured CORS middleware
     */
    public static function cors(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization'],
        int $maxAge = 3600
    ): callable {
        return new CorsMiddleware($allowedOrigins, $allowedMethods, $allowedHeaders, $maxAge);
    }

    /**
     * Creates a request sanitization middleware instance
     * 
     * Configures input sanitization for REST API requests to prevent
     * XSS attacks, SQL injection, and ensure data consistency.
     * 
     * @param array $rules Custom sanitization rules for specific fields
     * @param bool $stripTags Whether to strip HTML tags from input
     * @param bool $encodeSpecialChars Whether to encode special characters
     * @param array|null $allowedHtmlTags List of allowed HTML tags and attributes
     * @param string $encoding Character encoding to use
     * @return callable The configured sanitization middleware
     */
    public static function sanitization(
        array $rules = [],
        bool $stripTags = true,
        bool $encodeSpecialChars = true,
        ?array $allowedHtmlTags = null,
        string $encoding = 'UTF-8'
    ): callable {
        return new SanitizationMiddleware($rules, $stripTags, $encodeSpecialChars, $allowedHtmlTags, $encoding);
    }
}
