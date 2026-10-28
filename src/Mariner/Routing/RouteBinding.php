<?php

namespace Mariner\Routing;

use Closure;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\NutsAndBolts\Str;
use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Database\Instrument\Model;

class RouteBinding
{
    /**
     * Create a Route model binding for a given callback.
     *
     * @param  \Voyager\Vessel\ControlPanel  $container
     * @param  \Closure|string  $binder
     * @return \Closure
     */
    public static function forCallback($container, $binder)
    {
        if (is_string($binder)) {
            return static::createClassBinding($container, $binder);
        }

        return $binder;
    }

    /**
     * Create a class based binding using the IoC container.
     *
     * @param  \Voyager\Vessel\ControlPanel  $container
     * @param  string  $binding
     * @return \Closure
     */
    protected static function createClassBinding($container, $binding)
    {
        return function ($value, $route) use ($container, $binding) {
            // If the binding has an @ sign, we will assume it's being used to delimit
            // the class name from the bind method name. This allows for bindings
            // to run multiple bind methods in a single class for convenience.
            [$class, $method] = Str::parseCallback($binding, 'bind');

            $callable = [$container->make($class), $method];

            return $callable($value, $route);
        };
    }

    /**
     * Create a Route model binding for a model.
     *
     * @param  \Voyager\Vessel\ControlPanel  $container
     * @param  string  $class
     * @param  \Closure|null  $callback
     * @return \Closure
     *
     * @throws \Voyager\Database\Instrument\ModelNotFoundException<\Voyager\Database\Instrument\Model>
     */
    public static function forModel($container, $class, $callback = null)
    {
        return function ($value, $route = null) use ($container, $class, $callback) {
            if (is_null($value)) {
                return;
            }

            // For model binders, we will attempt to retrieve the models using the first
            // method on the model instance. If we cannot retrieve the models we'll
            // throw a not found exception otherwise we will return the instance.
            $instance = $container->make($class);

            $withTrashed = $route?->allowsTrashedBindings() && $instance instanceof Model && $instance::isSoftDeletable();

            $model = $instance instanceof UrlRoutable
                ? $instance->{$withTrashed ? 'resolveSoftDeletableRouteBinding' : 'resolveRouteBinding'}($value)
                : InstrumentRouteBinding::resolve($instance, $value, null, (bool) $withTrashed);

            if ($model) {
                return $model;
            }

            // If a callback was supplied to the method we will call that to determine
            // what we should do when the model is not found. This just gives these
            // developer a little greater flexibility to decide what will happen.
            if ($callback instanceof Closure) {
                return $callback($value);
            }

            throw (new ModelNotFoundException)->setModel($class);
        };
    }
}
