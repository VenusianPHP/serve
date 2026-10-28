<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Venusian\Serve\Tests\Integration\Fixtures\UserFactory as BaseUserFactory;

class UserFactory extends BaseUserFactory
{
    protected $model = User::class;
}
