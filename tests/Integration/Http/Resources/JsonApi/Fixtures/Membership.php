<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Relations\Pivot;

class Membership extends Pivot
{
    protected $table = 'team_user';
}
