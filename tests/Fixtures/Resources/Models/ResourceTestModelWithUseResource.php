<?php

namespace Venusian\Serve\Tests\Fixtures\Resources\Models;

use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\TransformsToResource;
use Venusian\Serve\Tests\Fixtures\Resources\TestJsonResource;
use Voyager\Database\Instrument\Model;

#[UseResource(TestJsonResource::class)]
class ResourceTestModelWithUseResource extends Model
{
    use TransformsToResource;
}
