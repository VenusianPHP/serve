<?php

return [

    /*
    |--------------------------------------------------------------------------
    | php computer serve
    |--------------------------------------------------------------------------
    |
    | Where the server listens, and how much it takes on. max_in_flight is how
    | many requests one worker handles at once, each in its own loop task.
    | Set https when serving behind a proxy that terminates TLS. The
    | health path answers 200 without the app; null turns it off.
    |
    */

    'host' => env('SERVE_HOST', '127.0.0.1'),

    'port' => (int) env('SERVE_PORT', 8020),

    'max_in_flight' => (int) env('SERVE_MAX_IN_FLIGHT', 10),

    // Worker processes sharing the port, balanced by the kernel: above 1 only on Linux.
    'workers' => (int) env('SERVE_WORKERS', 1),

    'https' => (bool) env('SERVE_HTTPS', false),

    'health' => '/up',

    // Answer GET and HEAD for files under public/ (never .php files) without the app, as a web server
    // in front of PHP would. False when something in front serves them.
    'static' => true,

    // Seconds for a request's line and headers; then for its body, between bytes; then for a
    // response, between bytes leaving. Each runs out to a 408 or a closed connection.
    'header_timeout' => 30.0,

    'body_timeout' => 30.0,

    'write_timeout' => 30.0,

    // Connections held at once (more wait in the system backlog); null is 1024, or fewer when the
    // process's open-file limit (ulimit -n) leaves room for fewer. And the answers queued for one
    // connection beyond which its requests stop being read.
    'max_connections' => env('SERVE_MAX_CONNECTIONS'),

    'max_buffered' => 1_048_576,

    'idle_timeout' => 60.0,

    // Seconds a stop (Ctrl-C, SIGTERM) waits for the requests in flight.
    'drain_timeout' => 30.0,

    'limits' => [
        'request_line' => 8_192,
        'header_bytes' => 16_384,
        'headers' => 100,
        'body' => 10_485_760,
    ],

];
