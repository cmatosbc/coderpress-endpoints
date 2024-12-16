<?php

namespace CoderPress\Tests\Rest\Middleware;

use CoderPress\Rest\Middleware\SanitizationMiddleware;
use Mockery;
use WP_REST_Request;
use WP_REST_Response;

class SanitizationMiddlewareTest extends \BaseTestCase
{
    private $middleware;
    private $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SanitizationMiddleware();
        $this->request = Mockery::mock(WP_REST_Request::class);
    }

    public function testSanitizesInputAccordingToRules()
    {
        $params = [
            'title' => '<p>TEST TITLE</p>',
            'content' => '<script>alert("xss")</script>Hello World'
        ];

        $this->request->shouldReceive('get_params')
            ->once()
            ->andReturn($params);

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('title', 'TEST TITLE');

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('content', 'Hello World');

        $response = new WP_REST_Response();
        $result = ($this->middleware)($this->request, function($request) use ($response) {
            return $response;
        });

        $this->assertSame($response, $result);
    }

    public function testHandlesNestedArrays()
    {
        $params = [
            'meta' => [
                'title' => '<p>TEST TITLE</p>',
                'description' => '<script>alert("xss")</script>Hello World'
            ]
        ];

        $this->request->shouldReceive('get_params')
            ->once()
            ->andReturn($params);

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('meta', [
                'title' => 'TEST TITLE',
                'description' => 'Hello World'
            ]);

        $response = new WP_REST_Response();
        $result = ($this->middleware)($this->request, function($request) use ($response) {
            return $response;
        });

        $this->assertSame($response, $result);
    }

    public function testPreservesNonStringValues()
    {
        $params = [
            'id' => 123,
            'active' => true,
            'price' => 99.99
        ];

        $this->request->shouldReceive('get_params')
            ->once()
            ->andReturn($params);

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('id', 123);

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('active', true);

        $this->request->shouldReceive('set_param')
            ->once()
            ->with('price', 99.99);

        $response = new WP_REST_Response();
        $result = ($this->middleware)($this->request, function($request) use ($response) {
            return $response;
        });

        $this->assertSame($response, $result);
    }
}
