<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Attributes\UseFactory;
use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;

#[UseFactory(PostFactory::class)]
#[UseResource(PostResource::class)]
class Post extends Model
{
    use HasFactory, TransformsToResource;

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
