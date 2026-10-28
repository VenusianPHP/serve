<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Attributes\UseFactory;
use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Factories\HasFactory;
use Mariner\Auth\User as Authenticatable;

#[UseResource(UserResource::class)]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use HasFactory, TransformsToResource;

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function chaperonePosts()
    {
        return $this->hasMany(Post::class)->chaperone('author');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps()
            ->using(Membership::class)
            ->as('membership');
    }
}
