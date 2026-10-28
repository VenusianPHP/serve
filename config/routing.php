<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Model Binding Pool
    |--------------------------------------------------------------------------
    |
    | Mariner binds Instrument models to route parameters with a query. Left
    | null, that query runs in place, as it always has. Set to 'thread',
    | 'process', or 'auto' (thread workers when bound, process workers
    | otherwise) to run it on a worker pool instead: a request handled in a
    | fiber the event loop runs then waits without holding the loop.
    |
    | Models implementing UrlRoutable resolve themselves and are not affected.
    |
    */

    'binding_via' => env('ROUTING_BINDING_VIA'),

    /*
    |--------------------------------------------------------------------------
    | Async Throttling
    |--------------------------------------------------------------------------
    |
    | Off, the throttle middleware reads and writes its counters in place. On,
    | it uses the rate limiter's async variant, so a request handled in a
    | fiber the event loop runs waits on the cache without holding the loop.
    | The limiter's cache store must have async operations. The Redis
    | throttle middleware keeps its own limiter and is not affected.
    |
    */

    'throttle_async' => (bool) env('ROUTING_THROTTLE_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Application Route Files
    |--------------------------------------------------------------------------
    |
    | Mariner loads these when the routes are not cached: 'web' in the "web"
    | middleware group, 'api' in the "api" group under the 'api_prefix'.
    | Set a file to null to load it yourself, or not at all. A file that
    | does not exist loads nothing. `php computer install:web` creates both.
    |
    */

    'web' => base_path('routes/web.php'),

    'api' => base_path('routes/api.php'),

    'api_prefix' => 'api',

];
