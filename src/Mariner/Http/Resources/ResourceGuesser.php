<?php

namespace Mariner\Http\Resources;

use LogicException;
use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\Attributes\UseResourceCollection;
use Mariner\Http\Resources\Json\JsonResource;
use Mariner\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use Voyager\NutsAndBolts\Str;

/**
 * Finds a model's resource the way Laravel v13.37.0's TransformsToResource and
 * TransformsToResourceCollection do: #[UseResource] / #[UseResourceCollection] on the model, then
 * App\Http\Resources\{Model}Resource / {Model}, by the model's place under a Models namespace.
 */
final class ResourceGuesser
{
    /**
     * The resource for a model.
     *
     * @throws \LogicException when no resource class is found
     */
    public static function forModel(object $model, ?string $resourceClass = null): JsonResource
    {
        if ($resourceClass !== null) {
            return $resourceClass::make($model);
        }

        $resourceClass = self::attribute($model::class, UseResource::class);

        if ($resourceClass !== null && class_exists($resourceClass)) {
            return $resourceClass::make($model);
        }

        foreach (self::guessResourceName($model::class) as $resourceClass) {
            if (class_exists($resourceClass)) {
                return $resourceClass::make($model);
            }
        }

        throw new LogicException(sprintf('Failed to find resource class for model [%s].', $model::class));
    }

    /**
     * The resource collection for a collection of models.
     *
     * @param  \Voyager\NutsAndBolts\Collection|\Voyager\Pagination\AbstractPaginator|\Voyager\Pagination\AbstractCursorPaginator  $items
     *
     * @throws \LogicException when the items are not models with TransformsToResource, or no resource class is found
     */
    public static function forCollection(object $items, ?string $resourceClass = null): ResourceCollection
    {
        if ($resourceClass !== null) {
            return $resourceClass::collection($items);
        }

        if ($items->isEmpty()) {
            return new ResourceCollection($items);
        }

        $model = method_exists($items, 'getCollection') ? $items->getCollection()->first() : $items->first();

        if (! is_object($model)) {
            throw new LogicException('Resource collection guesser expects the collection to contain objects.');
        }

        $className = $model::class;

        if (! method_exists($className, 'guessResourceName')) {
            throw new LogicException(sprintf('Expected class %s to implement guessResourceName method. Make sure the model uses the TransformsToResource trait.', $className));
        }

        $useResourceCollection = self::attribute($className, UseResourceCollection::class);

        if ($useResourceCollection !== null && class_exists($useResourceCollection)) {
            return new $useResourceCollection($items);
        }

        $useResource = self::attribute($className, UseResource::class);

        if ($useResource !== null && class_exists($useResource)) {
            return $useResource::collection($items);
        }

        $resourceClasses = $className::guessResourceName();

        foreach ($resourceClasses as $resourceClass) {
            if (class_exists($resourceCollection = $resourceClass.'Collection')) {
                return new $resourceCollection($items);
            }
        }

        foreach ($resourceClasses as $resourceClass) {
            if (class_exists($resourceClass)) {
                return $resourceClass::collection($items);
            }
        }

        throw new LogicException(sprintf('Failed to find resource class for model [%s].', $className));
    }

    /**
     * The conventional resource class names for a model under a Models namespace.
     *
     * @return list<string>
     */
    public static function guessResourceName(string $modelClass): array
    {
        if (! Str::contains($modelClass, '\\Models\\')) {
            return [];
        }

        $relativeNamespace = Str::after($modelClass, '\\Models\\');

        $relativeNamespace = Str::contains($relativeNamespace, '\\')
            ? Str::beforeLast($relativeNamespace, '\\'.class_basename($modelClass))
            : '';

        $potentialResource = sprintf(
            '%s\\Http\\Resources\\%s%s',
            Str::before($modelClass, '\\Models'),
            $relativeNamespace !== '' ? $relativeNamespace.'\\' : '',
            class_basename($modelClass)
        );

        return [$potentialResource.'Resource', $potentialResource];
    }

    private static function attribute(string $class, string $attribute): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        $attributes = (new ReflectionClass($class))->getAttributes($attribute);

        return $attributes !== [] ? $attributes[0]->newInstance()->class : null;
    }
}
