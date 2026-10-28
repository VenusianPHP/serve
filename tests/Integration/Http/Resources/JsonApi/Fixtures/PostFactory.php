<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Factories\Factory;

class PostFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'title' => 'title '.++self::$sequence,
            'content' => 'content of post '.self::$sequence,
        ];
    }

    #[\Override]
    public function modelName()
    {
        return Post::class;
    }
}
