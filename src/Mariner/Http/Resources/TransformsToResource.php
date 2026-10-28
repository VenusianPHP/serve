<?php

namespace Mariner\Http\Resources;

use Mariner\Http\Resources\Json\JsonResource;

/**
 * Laravel's Model::toResource(), opt in per model: Voyager's Instrument Model stays HTTP-agnostic,
 * so an app adds this trait to the models it turns into resources. Collections of such models get
 * toResourceCollection() from a macro HttpServiceProvider registers.
 */
trait TransformsToResource
{
    /**
     * Create a new resource object for the model.
     *
     * @param  class-string<\Mariner\Http\Resources\Json\JsonResource>|null  $resourceClass
     */
    public function toResource(?string $resourceClass = null): JsonResource
    {
        return ResourceGuesser::forModel($this, $resourceClass);
    }

    /**
     * The conventional resource class names for the model.
     *
     * @return list<string>
     */
    public static function guessResourceName(): array
    {
        return ResourceGuesser::guessResourceName(static::class);
    }
}
