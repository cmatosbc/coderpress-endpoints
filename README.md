# WP CoderPress Endpoints

This package intends to provide a more straightforward way to add custom endpoints to Wordpress Rest API (added to mu-plugins with no hooks, as a suggestion), also using a Facade pattern which allows:
* To attach PSR-16 compliant cache drivers of any nature
* To attach as many middlewares as you want to modify the request or provide a particular response
* Interfere in the way cached responses are stored and retrieved (JSON, serialize(), igbinary_serialize())
* Provide a port to add multiple custom endpoints without repeating code and sharing cache, middleware and variables within, including a dynamic endpoint generation
* Offer common middlewares to handle common scenarios in REST API development

# Basic Example

The following example creates a very simple example endpoint to the rest API that comes with two middlewares attached (as \Closure objects). The first one modifies any passed parameter to "id = 123", which triggers the second middleware and returns a WP_REST_Response - this also prevents the cache to be done.

If the second middleware is removed, the endpoint is no longer short-circuited, so the results are now serialized and cached in a file within wp-content/cache/. In the example, the cache is managed by FileCache, but this package also includes usable drivers for Redis (using redis-php extension) and MySQL cache, using a custom DB table.

```php
use CoderPress\Rest\{RestEndpointFacade, AbstractRestEndpoint};
use CoderPress\Cache\{RedisCache, MySqlCache, FileCache};

require __DIR__ . '/vendor/autoload.php';

$cacheInstance = new FileCache(WP_CONTENT_DIR . '/cache/');

$custom = RestEndpointFacade::createEndpoint(
    /** The five first arguments stand as they are for register_rest_route()
     * but with callbacks now accepting \Closure functions instead.
     * 
     * The middlewares arguments accepts an array of closures which will be
     * sequentially executed and MUST have a $request parameter.
     * 
     * The cache mechanism is automatically applied to the endpoint and accepts
     * any PSR-16 compliant object. Optionally, the expire time and the type
     * of serialization can be changed. Expires accepts any value in seconds as
     * integer or a Datetime object (and then the time in seconds between that and
     * the current time will be automatically extracted)
     * 
     */
    namespace: 'testing/v1', 
    route: 'custom/',
    args: [
      'id' => [
        'validate_callback' => function($param, $request, $key) {
          return is_numeric($param);
        }
      ],
    ],
    callback: function (\WP_REST_Request $request) {
        $postId = $request->get_param('id');
        $postCategoryIds = wp_get_object_terms($postId, ['category'], ['fields' => 'ids']);

        return get_posts([
          'post_status' => 'publish',
          'category__in' => $postCategoryIds,
          'order' => 'relevance'
        ]);
    },
    permissionCallback: function () {
        return is_user_logged_in();
    },
    /**
     * Accepts any number of /Closure middleware functions - to each one of them,
     * the $request object must be passed. For changes made to the WP_REST_Request object,
     * the closure must return void(). However, the middleware can also return a response
     * to the request easily by return a new WP_REST_Response object instead. 
     */
    middlewares: [
        function (\WP_REST_Request $request) {
            $request->set_param('id', 123);
        },
        function (\WP_REST_Request $request) {
            if ($request->get_param('id') == 123) {
                return new \WP_REST_Response(['message' => 'Invalid value'], 201);
            }
        }
    ],
    /**
     * Accepts any instance PSR-16 compliant (CacheInterface contract),
     * this package comes with 3 usable examples (file, Redis and custom MySQL
     * table caching drivers). 
     */
    cache: $cacheInstance,
    /**
     * Accepts 3 different serialize/unserialize methods - defaults to serialize(),
     * but can be using JSON - AbstractRestEndpoint::SERIALIZE_JSON - or igbinary PHP 
     * extension which offers a more efficient serializing version
     *  - AbstractRestEndpoint::SERIALIZE_IGBINARY. 
     */
    cacheSerializingMethod: AbstractRestEndpoint::SERIALIZE_PHP,
    /**
     * Accepts seconds as integer value, but also DateTime objects - for the latter,
     * the system will get the interval between NOW and the passed DateTime object time,
     * so the cache will work as scheduled.
     */
    cacheExpires: (new \DateTime())->modify('+1 day')
);
```

# Middlewares

The package includes several built-in middlewares to handle common scenarios in REST API development:

## CORS Middleware

The CORS (Cross-Origin Resource Sharing) middleware allows you to configure cross-origin requests to your API endpoints. Here's how to use it:

```php
use CoderPress\Rest\RestEndpointFacade;
use CoderPress\Rest\Middleware\MiddlewareFactory;

// Create an endpoint with CORS support
RestEndpointFacade::createEndpoint(
    namespace: 'api/v1',
    route: 'posts',
    callback: function($request) {
        return ['data' => 'Your API response'];
    },
    permissionCallback: function() {
        return true;
    },
    args: [],
    methods: ['GET', 'POST', 'OPTIONS'],
    middlewares: [
        MiddlewareFactory::cors(
            allowedOrigins: ['https://your-domain.com'],
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type', 'X-Custom-Header'],
            maxAge: 7200
        )
    ]
);
```

The CORS middleware provides:
- Origin validation against a whitelist
- Configurable HTTP methods
- Customizable allowed headers
- Preflight request handling
- Configurable cache duration for preflight responses
- Credentials support

All parameters are optional and come with sensible defaults for typical API usage.

## Sanitization Middleware

The Sanitization middleware provides comprehensive input sanitization and validation for your API endpoints. It helps prevent XSS attacks, SQL injection, and ensures data consistency:

```php
use CoderPress\Rest\RestEndpointFacade;
use CoderPress\Rest\Middleware\MiddlewareFactory;

RestEndpointFacade::createEndpoint(
    namespace: 'api/v1',
    route: 'posts',
    callback: function($request) {
        return ['data' => $request->get_params()];
    },
    permissionCallback: function() {
        return true;
    },
    args: [
        'title' => [
            'required' => true,
            'type' => 'string'
        ],
        'content' => [
            'required' => true,
            'type' => 'string'
        ]
    ],
    methods: ['POST'],
    middlewares: [
        MiddlewareFactory::sanitization(
            rules: [
                // Custom sanitization rules for specific fields
                'title' => fn($value) => sanitize_title($value),
                'content' => fn($value) => wp_kses_post($value)
            ],
            stripTags: true,
            encodeSpecialChars: true,
            allowedHtmlTags: [
                'p' => [],
                'a' => ['href' => [], 'title' => []],
                'b' => [],
                'i' => []
            ]
        )
    ]
);
```

The middleware provides:
- Field-specific sanitization rules using callbacks
- HTML tag stripping with configurable allowed tags
- Special character encoding
- UTF-8 validation
- Recursive array sanitization
- WordPress-specific sanitization functions integration
- XSS and SQL injection protection

All parameters are optional and come with secure defaults. Use the `rules` parameter to define custom sanitization logic for specific fields.