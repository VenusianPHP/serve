<?php

namespace Venusian\Serve\Tests\Integration\Http\Fixtures;

use Mariner\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    public function toArray($request)
    {
        return ['name' => $this->name];
    }
}
