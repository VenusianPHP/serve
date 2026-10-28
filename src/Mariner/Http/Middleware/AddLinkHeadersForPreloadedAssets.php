<?php

namespace Mariner\Http\Middleware;

use Mariner\Http\Response;
use Voyager\NutsAndBolts\Collection;
use Mariner\NutsAndBolts\Facades\Vite;

class AddLinkHeadersForPreloadedAssets
{
    /**
     * Configure the middleware.
     *
     * @param  int  $limit
     * @return string
     */
    public static function using($limit)
    {
        return static::class.':'.$limit;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|null  $limit
     * @return \Mariner\Http\Response
     */
    public function handle($request, $next, $limit = null)
    {
        return tap($next($request), function ($response) use ($limit) {
            if ($response instanceof Response && Vite::preloadedAssets() !== []) {
                $response->header('Link', (new Collection(Vite::preloadedAssets()))
                    ->when($limit, fn ($assets, $limit) => $assets->take($limit))
                    ->map(fn ($attributes, $url) => "<{$url}>; ".implode('; ', $attributes))
                    ->join(', '), false);
            }
        });
    }
}
