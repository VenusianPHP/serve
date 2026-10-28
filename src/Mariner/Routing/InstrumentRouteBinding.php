<?php

namespace Mariner\Routing;

use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsToMany;
use Voyager\Database\Instrument\Relations\HasManyThrough;
use Voyager\NutsAndBolts\Str;

/**
 * Route-model binding for Venusian's Instrument models, with Laravel's semantics.
 *
 * Venusian's models leave route binding out (receiving HTTP is Mariner's job), so Mariner binds any
 * Instrument model here: by the binding field or the model's route key, children through the
 * parent's relationship, trashed models where the route allows them. A model implementing
 * UrlRoutable resolves itself instead; BindsToRoutes gives such a model these same methods.
 */
final class InstrumentRouteBinding
{
    /**
     * The model a route segment names, or null.
     */
    /**
     * Whether a value stands for a route parameter by its route key: a UrlRoutable, or a plain
     * Instrument model, which keeps getRouteKey() without the contract.
     */
    public static function isRoutable(mixed $value): bool
    {
        return $value instanceof UrlRoutable || $value instanceof Model;
    }

    public static function resolve(Model $model, mixed $value, ?string $field = null, bool $withTrashed = false): ?Model
    {
        return self::first(self::bindingQuery($model, $value, $field, $withTrashed));
    }

    /**
     * The child a route segment names, found through the parent's relationship, or null.
     */
    public static function resolveChild(Model $parent, string $childType, mixed $value, ?string $field, bool $withTrashed = false): ?Model
    {
        return self::first(self::childBindingQuery($parent, $childType, $value, $field, $withTrashed));
    }

    /**
     * The query that finds the model a route segment names.
     */
    public static function bindingQuery(Model $model, mixed $value, ?string $field = null, bool $withTrashed = false): mixed
    {
        $query = self::where($model, $model->newQuery(), $value, $field);

        return $withTrashed ? $query->withTrashed() : $query;
    }

    /**
     * The query that finds a child through the parent's relationship (posts() for {post}).
     */
    public static function childBindingQuery(Model $parent, string $childType, mixed $value, ?string $field, bool $withTrashed = false): mixed
    {
        $relationship = $parent->{self::childRelationshipName($parent, $childType)}();

        // A relationship method may hand back the related model itself, as Laravel allows.
        $related = $relationship instanceof Model ? $relationship : $relationship->getRelated();

        $field = $field ?: $related->getRouteKeyName();

        if ($relationship instanceof HasManyThrough || $relationship instanceof BelongsToMany) {
            $field = $related->qualifyColumn($field);
        }

        $query = self::where($related, $relationship, $value, $field);

        return $withTrashed ? $query->withTrashed() : $query;
    }

    /**
     * Run a binding query: in place, or on a worker pool when config routing.binding_via names one.
     */
    private static function first(mixed $query): ?Model
    {
        $via = app('config')->get('routing.binding_via');

        if (is_null($via)) {
            return $query->first();
        }


        return $query->via($via === 'auto' ? null : $via)->first()->wait();
    }

    /**
     * The where clause for a route value; a model's own resolveRouteBindingQuery() wins.
     */
    private static function where(Model $model, mixed $query, mixed $value, ?string $field): mixed
    {
        return method_exists($model, 'resolveRouteBindingQuery')
            ? $model->resolveRouteBindingQuery($query, $value, $field)
            : $query->where($field ?? $model->getRouteKeyName(), $value);
    }

    private static function childRelationshipName(Model $parent, string $childType): string
    {
        return method_exists($parent, 'childRouteBindingRelationshipName')
            ? $parent->childRouteBindingRelationshipName($childType)
            : Str::plural(Str::camel($childType));
    }
}
