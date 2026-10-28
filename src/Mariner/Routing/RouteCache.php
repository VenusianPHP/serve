<?php

namespace Mariner\Routing;

use Voyager\Contracts\Core\CachesConfiguration;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\DataObjects\Env;

/**
 * Where the compiled route cache lives, and whether the application is running from it.
 *
 * The path follows Venusian's other caches: APP_ROUTES_CACHE when set (absolute, or relative to
 * the base path), otherwise routes-v7.php beside the config cache, which already lives under the
 * packaged data path when the app runs as a bundled binary.
 */
class RouteCache
{
    public function __construct(
        protected FrameworkCore $app,
        protected Filesystem $files,
    ) {}

    public function path(): string
    {
        if (! is_null($env = Env::get('APP_ROUTES_CACHE'))) {
            return str_starts_with($env, '/') || str_starts_with($env, '\\') ? $env : $this->app->basePath($env);
        }

        $directory = $this->app instanceof CachesConfiguration
            ? dirname($this->app->getCachedConfigPath())
            : $this->app->make('path.bootstrap').DIRECTORY_SEPARATOR.'cache';

        return $directory.DIRECTORY_SEPARATOR.'routes-v7.php';
    }

    public function isCached(): bool
    {
        return $this->files->exists($this->path());
    }

    /**
     * Replace the router's routes with the cached, compiled ones.
     */
    public function load(): void
    {
        require $this->path();
    }

    public function clear(): void
    {
        $this->files->delete($this->path());
    }
}
