<?php

namespace CoderPress\Tests\Rest\Middleware;

use Brain\Monkey\Functions;
use CoderPress\Rest\Middleware\CorsMiddleware;
use WP_REST_Request;
use WP_REST_Response;

class CorsMiddlewareTest extends \BaseTestCase
{
    private CorsMiddleware $middleware;
    private WP_REST_Request $request;
    private WP_REST_Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new CorsMiddleware(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type'],
            maxAge: 3600
        );

        $this->request = new WP_REST_Request();
        $this->response = new WP_REST_Response();
    }

    public function testAddsCorsHeadersForAllowedOrigin()
    {
        // Create a mock for get_header to return our test origin
        $this->request = $this->getMockBuilder(WP_REST_Request::class)
            ->onlyMethods(['get_header'])
            ->getMock();

        $this->request->expects($this->once())
            ->method('get_header')
            ->with('origin')
            ->willReturn('https://example.com');

        $next = fn() => $this->response;
        
        $result = ($this->middleware)($this->request, $next);
        
        $headers = $result->get_headers();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertEquals('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayHasKey('Access-Control-Allow-Credentials', $headers);
        $this->assertEquals('true', $headers['Access-Control-Allow-Credentials']);
    }

    public function testHandlesPreflightRequest()
    {
        // Create a mock for both get_header and get_method
        $this->request = $this->getMockBuilder(WP_REST_Request::class)
            ->onlyMethods(['get_header', 'get_method'])
            ->getMock();

        $this->request->expects($this->once())
            ->method('get_header')
            ->with('origin')
            ->willReturn('https://example.com');

        $this->request->expects($this->once())
            ->method('get_method')
            ->willReturn('OPTIONS');

        $next = fn() => $this->response;
        
        $result = ($this->middleware)($this->request, $next);
        
        $headers = $result->get_headers();
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertEquals('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertEquals('GET, POST', $headers['Access-Control-Allow-Methods']);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertEquals('Content-Type', $headers['Access-Control-Allow-Headers']);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
        $this->assertEquals(3600, $headers['Access-Control-Max-Age']);
    }

    public function testIgnoresDisallowedOrigin()
    {
        $this->request = $this->getMockBuilder(WP_REST_Request::class)
            ->onlyMethods(['get_header'])
            ->getMock();

        $this->request->expects($this->once())
            ->method('get_header')
            ->with('origin')
            ->willReturn('https://unauthorized.com');

        $next = fn() => $this->response;
        
        $result = ($this->middleware)($this->request, $next);
        
        $headers = $result->get_headers();
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Credentials', $headers);
        $this->assertEquals('true', $headers['Access-Control-Allow-Credentials']);
    }
}
