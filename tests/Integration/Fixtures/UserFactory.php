<?php

namespace Venusian\Serve\Tests\Integration\Fixtures;

use Carbon\Carbon;
use Mariner\Auth\User;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\NutsAndBolts\Str;

/**
 * The users the integration tests create: Testbench's UserFactory, for Mariner's user model.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    private static int $sequence = 0;

    public function definition(): array
    {
        // No faker here: a counter keeps the names and addresses unique.
        $n = ++self::$sequence;

        return [
            'name' => "User {$n}",
            'email' => "user-{$n}@example.test",
            'email_verified_at' => Carbon::now(),
            'password' => static::$password ??= app('hash')->make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** The user's email address is not verified. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }
}
