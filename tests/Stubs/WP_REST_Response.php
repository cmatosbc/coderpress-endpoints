<?php

/**
 * Test double for WordPress WP_REST_Response class
 */
class WP_REST_Response
{
    private $headers = [];

    public function header($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function get_headers()
    {
        return $this->headers;
    }
}
