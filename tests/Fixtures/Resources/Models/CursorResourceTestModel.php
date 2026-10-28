<?php

namespace Venusian\Serve\Tests\Fixtures\Resources\Models;

use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Model;

class CursorResourceTestModel extends Model
{
    use TransformsToResource;
}
