<?php

namespace Venusian\Serve\Tests\Fixtures\Resources\Models;

use Mariner\Http\Resources\Attributes\UseResourceCollection;
use Mariner\Http\Resources\TransformsToResource;
use Venusian\Serve\Tests\Fixtures\Resources\TestJsonResourceCollection;
use Voyager\Database\Instrument\Model;

#[UseResourceCollection(TestJsonResourceCollection::class)]
class ResourceTestModelWithUseResourceCollection extends Model
{
    use TransformsToResource;
}
