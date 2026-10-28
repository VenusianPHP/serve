<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Factories\Factory;

class TeamFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'name' => 'Team '.++self::$sequence,
            'user_id' => UserFactory::new(),
            'personal_team' => true,
        ];
    }

    #[\Override]
    public function modelName()
    {
        return Team::class;
    }
}
