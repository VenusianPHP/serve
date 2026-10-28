<?php

use Mariner\Contracts\Routing\ResponseFactory;
use Mariner\Contracts\Routing\UrlGenerator;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Response as MarinerResponse;
use Mariner\Routing\Redirector;
use Mariner\NutsAndBolts\Facades\Route;
use League\Uri\Contracts\UriInterface;
use Symfony\Component\HttpFoundation\Response;
use Mariner\Routing\Uri;

if (! function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param  \BackedEnum|string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     */
    function route($name, $parameters = [], $absolute = true): string
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (! function_exists('action')) {
    /**
     * Generate the URL to a controller action.
     *
     * @param  string|array  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     */
    function action($name, $parameters = [], $absolute = true): string
    {
        return app('url')->action($name, $parameters, $absolute);
    }
}

if (! function_exists('url')) {
    /**
     * Generate a URL for the application.
     *
     * @param  string|null  $path
     * @param  mixed  $parameters
     * @param  bool|null  $secure
     * @return ($path is null ? \Mariner\Contracts\Routing\UrlGenerator : string)
     */
    function url($path = null, $parameters = [], $secure = null): UrlGenerator|string
    {
        if (is_null($path)) {
            return app(UrlGenerator::class);
        }

        return app(UrlGenerator::class)->to($path, $parameters, $secure);
    }
}

if (! function_exists('secure_url')) {
    /**
     * Generate a HTTPS url for the application.
     *
     * @param  string  $path
     * @param  mixed  $parameters
     * @return string
     */
    function secure_url($path, $parameters = [])
    {
        return url($path, $parameters, true);
    }
}

if (! function_exists('asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     */
    function asset($path, $secure = null): string
    {
        return app('url')->asset($path, $secure);
    }
}

if (! function_exists('secure_asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     */
    function secure_asset($path): string
    {
        return asset($path, true);
    }
}

if (! function_exists('uri')) {
    /**
     * Generate a URI for the application.
     */
    function uri(UriInterface|Stringable|array|string $uri, mixed $parameters = [], bool $absolute = true): Uri
    {
        return match (true) {
            is_array($uri) || str_contains($uri, '\\') => Uri::action($uri, $parameters, $absolute),
            str_contains($uri, '.') && Route::has($uri) => Uri::route($uri, $parameters, $absolute),
            default => Uri::of($uri),
        };
    }
}

if (! function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param  string|null  $to
     * @param  int  $status
     * @param  array  $headers
     * @param  bool|null  $secure
     * @return ($to is null ? \Mariner\Routing\Redirector : \Mariner\Http\RedirectResponse)
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null): Redirector|RedirectResponse
    {
        if (is_null($to)) {
            return app('redirect');
        }

        return app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (! function_exists('back')) {
    /**
     * Create a new redirect response to the previous location.
     *
     * @param  int  $status
     * @param  array  $headers
     * @param  mixed  $fallback
     */
    function back($status = 302, $headers = [], $fallback = false): RedirectResponse
    {
        return app('redirect')->back($status, $headers, $fallback);
    }
}

if (! function_exists('to_route')) {
    /**
     * Create a new redirect response to a named route.
     *
     * @param  \BackedEnum|string  $route
     * @param  mixed  $parameters
     * @param  int  $status
     * @param  array  $headers
     * @return \Mariner\Http\RedirectResponse
     */
    function to_route($route, $parameters = [], $status = 302, $headers = [])
    {
        return redirect()->route($route, $parameters, $status, $headers);
    }
}

if (! function_exists('to_action')) {
    /**
     * Create a new redirect response to a controller action.
     *
     * @param  string|array  $action
     * @param  mixed  $parameters
     * @param  int  $status
     * @param  array  $headers
     * @return \Mariner\Http\RedirectResponse
     */
    function to_action($action, $parameters = [], $status = 302, $headers = [])
    {
        return redirect()->action($action, $parameters, $status, $headers);
    }
}

if (! function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param  \Mariner\Contracts\View\View|string|array|null  $content
     * @param  int  $status
     * @return ($content is null ? \Mariner\Contracts\Routing\ResponseFactory : \Mariner\Http\Response)
     */
    function response($content = null, $status = 200, array $headers = []): ResponseFactory|MarinerResponse
    {
        $factory = app(ResponseFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content ?? '', $status, $headers);
    }
}
