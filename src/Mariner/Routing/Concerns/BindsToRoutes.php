<?php

namespace Mariner\Routing\Concerns;

use Mariner\Routing\InstrumentRouteBinding;
use Voyager\NutsAndBolts\Str;

/**
 * Laravel's route-binding methods for an Instrument model that implements UrlRoutable, so it can
 * override one of them (a custom query, another relationship name) and keep the rest. Models that
 * don't implement UrlRoutable are bound the same way without it.
 */
trait BindsToRoutes
{
    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBindingQuery($this, $value, $field)->first();
    }

    /**
     * Retrieve the model for a bound value, trashed ones included.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBindingQuery($this, $value, $field)->withTrashed()->first();
    }

    /**
     * Retrieve the child model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return InstrumentRouteBinding::childBindingQuery($this, $childType, $value, $field)->first();
    }

    /**
     * Retrieve the child model for a bound value, trashed ones included.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveSoftDeletableChildRouteBinding($childType, $value, $field)
    {
        return InstrumentRouteBinding::childBindingQuery($this, $childType, $value, $field, true)->first();
    }

    /**
     * Retrieve the child route model binding relationship name for the given child type.
     *
     * @param  string  $childType
     * @return string
     */
    public function childRouteBindingRelationshipName($childType)
    {
        return Str::plural(Str::camel($childType));
    }

    /**
     * Retrieve the query for a bound value.
     *
     * @param  mixed  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value);
    }
}
