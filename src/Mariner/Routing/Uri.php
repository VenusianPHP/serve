<?php

namespace Mariner\Routing;

use Closure;
use Mariner\Contracts\NutsAndBolts\Responsable;
use Mariner\Http\RedirectResponse;
use Voyager\NutsAndBolts\Uri as BaseUri;

/**
 * Venusian's Uri, made web-aware: built from routes and actions through the URL generator,
 * returnable from a controller as a redirect, and turning routable models in query values into
 * their route keys.
 */
class Uri extends BaseUri implements Responsable
{
    /**
     * The URL generator resolver.
     */
    protected static ?Closure $urlGeneratorResolver = null;

    /**
     * Get a URI instance of an absolute URL for the given path.
     */
    public static function to(string $path): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->to($path));
    }

    /**
     * Get a URI instance for a named route.
     *
     * @param  \BackedEnum|string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException|\InvalidArgumentException
     */
    public static function route($name, $parameters = [], $absolute = true): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->route($name, $parameters, $absolute));
    }

    /**
     * Create a signed route URI instance for a named route.
     *
     * @param  \BackedEnum|string  $name
     * @param  mixed  $parameters
     * @param  \DateTimeInterface|\DateInterval|int|null  $expiration
     * @param  bool  $absolute
     *
     * @throws \InvalidArgumentException
     */
    public static function signedRoute($name, $parameters = [], $expiration = null, $absolute = true): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->signedRoute($name, $parameters, $expiration, $absolute));
    }

    /**
     * Create a temporary signed route URI instance for a named route.
     *
     * @param  \BackedEnum|string  $name
     * @param  \DateTimeInterface|\DateInterval|int  $expiration
     * @param  array  $parameters
     * @param  bool  $absolute
     */
    public static function temporarySignedRoute($name, $expiration, $parameters = [], $absolute = true): static
    {
        return static::signedRoute($name, $parameters, $expiration, $absolute);
    }

    /**
     * Get a URI instance for a controller action.
     *
     * @param  string|array  $action
     * @param  mixed  $parameters
     * @param  bool  $absolute
     *
     * @throws \InvalidArgumentException
     */
    public static function action($action, $parameters = [], $absolute = true): static
    {
        return new static(call_user_func(static::$urlGeneratorResolver)->action($action, $parameters, $absolute));
    }

    /**
     * Create a redirect HTTP response for the given URI.
     */
    public function redirect(int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($this->value(), $status, $headers);
    }

    /**
     * Create an HTTP response that represents the URI object.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return new RedirectResponse($this->value());
    }

    /**
     * Set the URL generator resolver.
     */
    public static function setUrlGeneratorResolver(Closure $urlGeneratorResolver): void
    {
        static::$urlGeneratorResolver = $urlGeneratorResolver;
    }

    /**
     * Route-bindable models in a query become their route keys.
     */
    protected function queryValue(mixed $value): mixed
    {
        return InstrumentRouteBinding::isRoutable($value) ? $value->getRouteKey() : parent::queryValue($value);
    }
}
