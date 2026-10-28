<?php

namespace Venusian\Serve\Tests\Integration\Http\Fixtures;

use Mariner\Http\Resources\Json\ResourceCollection;

class PostModelCollectionResource extends ResourceCollection
{
    public $collects = Post::class;

    public function toArray($request)
    {
        return ['data' => $this->collection];
    }
}
