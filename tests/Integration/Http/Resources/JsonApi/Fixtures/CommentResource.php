<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Mariner\Http\Resources\JsonApi\JsonApiResource;

class CommentResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'content',
    ];

    /**
     * The resource's relationships.
     */
    public $relationships = [
        'posts',
        'commenter' => UserResource::class,
    ];
}
