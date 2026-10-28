<?php

namespace Venusian\Serve\Tests\Integration\Http\Fixtures;

use Voyager\Database\Instrument\Model;

class Post extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    /**
     * Return whether the post is published.
     *
     * @return bool
     */
    public function getIsPublishedAttribute()
    {
        return true;
    }
}
