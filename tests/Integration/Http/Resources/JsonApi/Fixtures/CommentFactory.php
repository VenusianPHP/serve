<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Factories\Factory;

class CommentFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'post_id' => PostFactory::new(),
            'user_id' => UserFactory::new(),
            'content' => 'comment '.++self::$sequence,
        ];
    }
}
