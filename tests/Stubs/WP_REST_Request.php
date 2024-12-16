<?php

/**
 * Test double for WordPress WP_REST_Request class
 */
class WP_REST_Request
{
    public function get_header($header)
    {
        return '';
    }

    public function get_method()
    {
        return 'GET';
    }

    public function get_params()
    {
        return [];
    }

    public function set_param($param, $value)
    {
        return true;
    }
}
