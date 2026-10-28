<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Attributes\UseFactory;
use Mariner\Http\Resources\Attributes\UseResource;
use Mariner\Http\Resources\TransformsToResource;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;

#[UseResource(ProfileResource::class)]
#[UseFactory(ProfileFactory::class)]
class Profile extends Model
{
    use HasFactory, TransformsToResource;

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
