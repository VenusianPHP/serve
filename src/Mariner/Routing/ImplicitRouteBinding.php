<?php

namespace Mariner\Routing;

use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Database\Instrument\ModelNotFoundException;
use Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Voyager\NutsAndBolts\Reflector;
use Voyager\NutsAndBolts\Str;
use Voyager\Database\Instrument\Model;

class ImplicitRouteBinding
{
    /**
     * Resolve the implicit route bindings for the given route.
     *
     * @param  \Voyager\Vessel\ControlPanel  $container
     * @param  \Mariner\Routing\Route  $route
     * @return void
     *
     * @throws \Voyager\Database\Instrument\ModelNotFoundException<\Voyager\Database\Instrument\Model>
     * @throws \Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException
     */
    public static function resolveForRoute($container, $route)
    {
        $parameters = $route->parameters();

        $route = static::resolveBackedEnumsForRoute($route, $parameters);

        // Instrument models bind too: Venusian leaves route binding out of them (see InstrumentRouteBinding).
        $bindable = array_filter($route->signatureParameters(), fn ($parameter) => Reflector::isParameterSubclassOf($parameter, UrlRoutable::class)
            || Reflector::isParameterSubclassOf($parameter, Model::class));

        foreach ($bindable as $parameter) {
            if (! $parameterName = static::getParameterName($parameter->getName(), $parameters)) {
                continue;
            }

            $parameterValue = $parameters[$parameterName];

            if ($parameterValue instanceof UrlRoutable) {
                continue;
            }

            $instance = $container->make(Reflector::getParameterClassName($parameter));

            $parent = $route->parentOfParameter($parameterName);

            $withTrashed = $route->allowsTrashedBindings() && $instance instanceof Model && $instance::isSoftDeletable();
            $field = $route->bindingFieldFor($parameterName);

            if (($parent instanceof UrlRoutable || $parent instanceof Model) &&
                ! $route->preventsScopedBindings() &&
                ($route->enforcesScopedBindings() || array_key_exists($parameterName, $route->bindingFields()))) {
                $model = $parent instanceof UrlRoutable
                    ? $parent->{$withTrashed ? 'resolveSoftDeletableChildRouteBinding' : 'resolveChildRouteBinding'}($parameterName, $parameterValue, $field)
                    : InstrumentRouteBinding::resolveChild($parent, $parameterName, $parameterValue, $field, $withTrashed);
            } else {
                $model = $instance instanceof UrlRoutable
                    ? $instance->{$withTrashed ? 'resolveSoftDeletableRouteBinding' : 'resolveRouteBinding'}($parameterValue, $field)
                    : InstrumentRouteBinding::resolve($instance, $parameterValue, $field, $withTrashed);
            }

            if (! $model) {
                throw (new ModelNotFoundException)->setModel(get_class($instance), [$parameterValue]);
            }

            $route->setParameter($parameterName, $model);
        }
    }

    /**
     * Resolve the Backed Enums route bindings for the route.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  array  $parameters
     * @return \Mariner\Routing\Route
     *
     * @throws \Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException
     */
    protected static function resolveBackedEnumsForRoute($route, $parameters)
    {
        foreach ($route->signatureParameters(['backedEnum' => true]) as $parameter) {
            if (! $parameterName = static::getParameterName($parameter->getName(), $parameters)) {
                continue;
            }

            $parameterValue = $parameters[$parameterName];

            if ($parameterValue === null) {
                continue;
            }

            $backedEnumClass = $parameter->getType()?->getName();

            $backedEnum = $parameterValue instanceof $backedEnumClass
                ? $parameterValue
                : $backedEnumClass::tryFrom((string) $parameterValue);

            if (is_null($backedEnum)) {
                throw new BackedEnumCaseNotFoundException($backedEnumClass, $parameterValue);
            }

            $route->setParameter($parameterName, $backedEnum);
        }

        return $route;
    }

    /**
     * Return the parameter name if it exists in the given parameters.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return string|null
     */
    protected static function getParameterName($name, $parameters)
    {
        if (array_key_exists($name, $parameters)) {
            return $name;
        }

        if (array_key_exists($snakedName = Str::snake($name), $parameters)) {
            return $snakedName;
        }

        return null;
    }
}
