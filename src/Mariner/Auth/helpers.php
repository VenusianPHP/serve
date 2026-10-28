<?php

use Mariner\Contracts\Auth\Access\Gate;
use Mariner\Contracts\Auth\Factory as AuthFactory;
use Mariner\Contracts\Auth\Guard;

if (! function_exists('auth')) {
    /**
     * Get the available auth instance.
     *
     * @param  string|null  $guard
     * @return ($guard is null ? \Mariner\Contracts\Auth\Factory : \Mariner\Contracts\Auth\Guard)
     */
    function auth($guard = null): AuthFactory|Guard
    {
        if (is_null($guard)) {
            return app(AuthFactory::class);
        }

        return app(AuthFactory::class)->guard($guard);
    }
}

if (! function_exists('policy')) {
    /**
     * Get a policy instance for a given class.
     *
     * @param  object|string  $class
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    function policy($class)
    {
        return app(Gate::class)->getPolicyFor($class);
    }
}
