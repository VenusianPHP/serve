<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Mariner\Http\Request;
use Mariner\Http\Resources\JsonApi\JsonApiResource;

class ProfileResource extends JsonApiResource
{
    protected array $relationships = [
        'user' => UserResource::class,
    ];

    #[\Override]
    public function toAttributes(Request $request)
    {
        return [
            'timezone' => $this->timezone,
            'date_of_birth' => $this->date_of_birth,
        ];
    }
}
