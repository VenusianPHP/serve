<?php

namespace Venusian\Serve\Tests\Integration\Http\Fixtures;

use Voyager\Database\Instrument\Model;

class Author extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];
}
