<?php

namespace CoderPress\Rest\Middleware;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Request Sanitization Middleware
 * 
 * Provides comprehensive input sanitization for API requests.
 * Supports custom sanitization rules, HTML handling, and character encoding.
 * Helps prevent XSS attacks and SQL injection.
 * 
 * @package CoderPress\Rest\Middleware
 * @author Carlos Matos | Team CoderPress
 * @since 1.0.0
 */
class SanitizationMiddleware
{
    /**
     * @var array Custom sanitization rules for specific fields
     */
    private array $rules;

    /**
     * @var bool Whether to strip HTML tags from input
     */
    private bool $stripTags;

    /**
     * @var bool Whether to encode special characters
     */
    private bool $encodeSpecialChars;

    /**
     * @var array|null List of allowed HTML tags and attributes
     */
    private ?array $allowedHtmlTags;

    /**
     * @var string Character encoding to use
     */
    private string $encoding;

    /**
     * Constructor for the SanitizationMiddleware
     * 
     * @param array $rules Custom sanitization rules for specific fields
     * @param bool $stripTags Whether to strip HTML tags from input
     * @param bool $encodeSpecialChars Whether to encode special characters
     * @param array|null $allowedHtmlTags List of allowed HTML tags and attributes
     * @param string $encoding Character encoding to use
     */
    public function __construct(
        array $rules = [],
        bool $stripTags = true,
        bool $encodeSpecialChars = true,
        ?array $allowedHtmlTags = null,
        string $encoding = 'UTF-8'
    ) {
        $this->rules = $rules;
        $this->stripTags = $stripTags;
        $this->encodeSpecialChars = $encodeSpecialChars;
        $this->allowedHtmlTags = $allowedHtmlTags;
        $this->encoding = $encoding;
    }

    /**
     * Middleware invocation handler
     * 
     * Processes and sanitizes all request parameters according to configuration.
     * 
     * @param WP_REST_Request $request The incoming request object
     * @param callable $next The next middleware in the chain
     * @return WP_REST_Response The response with sanitized parameters
     */
    public function __invoke(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        $params = $request->get_params();
        $sanitizedParams = $this->sanitizeData($params);
        
        foreach ($sanitizedParams as $key => $value) {
            $request->set_param($key, $value);
        }

        return $next($request);
    }

    /**
     * Recursively sanitizes an array of data
     * 
     * @param mixed $data The data to sanitize
     * @param string $path Current path for nested fields
     * @return mixed The sanitized data
     */
    private function sanitizeData($data, $path = '')
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $currentPath = $path ? "$path.$key" : $key;
                $sanitized[$key] = $this->sanitizeValue($value, $currentPath);
            }
            return $sanitized;
        }

        return $this->sanitizeValue($data, $path);
    }

    /**
     * Sanitizes a single value
     * 
     * Applies custom rules, strips tags, encodes special characters,
     * and performs additional sanitization as configured.
     * 
     * @param mixed $value The value to sanitize
     * @param string $path Field path for rule matching
     * @return mixed The sanitized value
     */
    private function sanitizeValue($value, string $path)
    {
        if (is_array($value)) {
            return $this->sanitizeData($value, $path);
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $value = wp_check_invalid_utf8($value);

        // Apply custom rules first
        if (isset($this->rules[$path])) {
            $value = call_user_func($this->rules[$path], $value);
        }

        // Strip HTML tags if enabled
        if ($this->stripTags) {
            if ($this->allowedHtmlTags) {
                $value = wp_kses($value, $this->allowedHtmlTags);
            } else {
                $value = wp_strip_all_tags($value, true);
            }
        }

        // Encode special characters if enabled
        if ($this->encodeSpecialChars && !$this->stripTags) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, $this->encoding, false);
        }
        
        return $value;
    }
}
