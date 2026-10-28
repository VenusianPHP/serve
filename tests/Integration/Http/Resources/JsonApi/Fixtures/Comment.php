<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Attributes\UseFactory;
use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;

#[UseFactory(CommentFactory::class)]
#[UseResource(CommentResource::class)]
class Comment extends Model
{
    use HasFactory, TransformsToResource;

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function commenter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
