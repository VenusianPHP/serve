<?php

/*
|--------------------------------------------------------------------------
| Web Application Settings
|--------------------------------------------------------------------------
|
| Mariner's own settings, read as config('webapp.*'). Publish this file to
| change them; they never touch the application's config/app.php.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | The URL generator uses this when it builds URLs outside of a request, such
    | as from a Computer command. Set it to the root of your application.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    |
    | The default locale for the translator, and the locale to fall back to when
    | a translation is missing in the current one.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode
    |--------------------------------------------------------------------------
    |
    | Where `php computer down` keeps the application's maintenance state. The
    | "file" driver writes storage/framework/down; the "cache" driver keeps it
    | in a cache store, so every machine sharing the store goes down together.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE'),
    ],

];
