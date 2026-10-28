<?php

namespace Mariner\Http\Resources\JsonApi;

use Closure;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;

/**
 * @internal
 */
class RelationResolver
{
    /**
     * The relation resolver.
     *
     * @var \Closure(mixed):(\Voyager\Database\Instrument\Collection|\Voyager\Database\Instrument\Model|\Mariner\Http\Resources\JsonApi\JsonApiResource|\Mariner\Http\Resources\JsonApi\AnonymousResourceCollection|null)
     */
    public Closure $relationResolver;

    /**
     * The relation resource class.
     *
     * @var class-string<\Mariner\Http\Resources\JsonApi\JsonApiResource>|null
     */
    public ?string $relationResourceClass = null;

    /**
     * Construct a new resource relationship resolver.
     *
     * @param  \Closure(mixed):(\Voyager\Database\Instrument\Collection|\Voyager\Database\Instrument\Model|\Mariner\Http\Resources\JsonApi\JsonApiResource|\Mariner\Http\Resources\JsonApi\AnonymousResourceCollection|null)|class-string<\Mariner\Http\Resources\JsonApi\JsonApiResource>|null  $resolver
     */
    public function __construct(public string $relationName, Closure|string|null $resolver = null)
    {
        $this->relationResolver = match (true) {
            $resolver instanceof Closure => $resolver,
            default => fn ($resource) => $resource->getRelation($this->relationName),
        };

        if (is_string($resolver) && class_exists($resolver)) {
            $this->relationResourceClass = $resolver;
        }
    }

    /**
     * Resolve the relation for a resource.
     */
    public function handle(mixed $resource): Collection|Model|null
    {
        $resolved = value($this->relationResolver, $resource);

        if ($resolved instanceof AnonymousResourceCollection) {
            $this->relationResourceClass ??= $resolved->collects;

            return new Collection($resolved->collection->map->resource);
        }

        if ($resolved instanceof JsonApiResource) {
            $this->relationResourceClass ??= $resolved::class;

            return $resolved->resource;
        }

        return $resolved;
    }

    /**
     * Get the resource class.
     *
     * @return class-string<\Mariner\Http\Resources\JsonApi\JsonApiResource>|null
     */
    public function resourceClass(): ?string
    {
        return $this->relationResourceClass;
    }
}
