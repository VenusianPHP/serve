<?php

namespace Venusian\Serve\Tests\Fixtures\Resources\Models;

use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Model;

class ResourceTestModel extends Model
{
    use TransformsToResource;
}
