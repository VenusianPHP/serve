<?php

namespace Venusian\Serve\Tests\Fixtures;

use Voyager\Database\Instrument\Model;

/** Plain Instrument models, as an application has them, on the fixture's 'binding' connection. */
class Author extends Model
{
    protected $connection = 'binding';

    protected $guarded = [];

    public $timestamps = false;

    public function articles()
    {
        return $this->hasMany(Article::class);
    }
}
