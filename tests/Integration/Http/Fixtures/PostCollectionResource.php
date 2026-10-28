<?php

namespace Venusian\Serve\Tests\Integration\Http\Fixtures;

use Mariner\Http\Resources\Json\ResourceCollection;

class PostCollectionResource extends ResourceCollection
{
    public $collects = PostResource::class;

    public function toArray($request)
    {
        return ['data' => $this->collection];
    }
}
