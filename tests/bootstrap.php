<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs/WP_REST_Request.php';
require_once __DIR__ . '/Stubs/WP_REST_Response.php';

// Setup Brain\Monkey
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for all tests
 */
abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Define WordPress function stubs
        Functions\when('wp_strip_all_tags')->alias(function($string, $keep_line_breaks = false) {
            // Remove script tags and their content first
            $string = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $string);
            // Then remove all remaining HTML tags
            return strip_tags($string);
        });

        Functions\when('wp_kses')->alias(function($string, $allowed_html) {
            return strip_tags($string);
        });

        Functions\when('wp_check_invalid_utf8')->alias(function($string) {
            return $string;
        });

        Functions\when('is_user_logged_in')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
