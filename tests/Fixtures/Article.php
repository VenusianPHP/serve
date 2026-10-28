<?php

namespace Venusian\Serve\Tests\Fixtures;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $connection = 'binding';

    protected $guarded = [];

    public $timestamps = false;
}
