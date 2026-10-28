<?php

namespace Venusian\Serve\Tests\Fixtures;

use Closure;
use Voyager\NutsAndBolts\Collection;
use Voyager\Signals\SignalDispatcher;

/**
 * A SignalDispatcher that records every signal it dispatches, for the assertions Laravel's EventFake gave.
 */
class SignalRecorder extends SignalDispatcher
{
    /** @var array<string, list<object|string>> */
    private array $recorded = [];

    public function __construct()
    {
        parent::__construct();

        $this->listen('*', function (string $name, array $payload) {
            $signal = $payload[0] ?? $name;

            $this->recorded[is_object($signal) ? $signal::class : $name][] = $signal;
        });
    }

    /** The dispatcher to hand to code under test; the recorder is one. */
    public function dispatcher(): static
    {
        return $this;
    }

    /** The recorded signals of a class, optionally those a callback accepts. */
    public function dispatched(string $signal, ?Closure $callback = null): Collection
    {
        return (new Collection($this->recorded[$signal] ?? []))->filter(fn ($s) => ! $callback || $callback($s))->values();
    }

    public function assertDispatched(string $signal, Closure|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertDispatchedTimes($signal, $callback);

            return;
        }

        expect($this->dispatched($signal, $callback))->not->toBeEmpty();
    }

    public function assertDispatchedTimes(string $signal, int $times): void
    {
        $actual = count($this->recorded[$signal] ?? []);

        expect($actual)->toBe($times, "[{$signal}] was dispatched {$actual} times, expected {$times}.");
    }

    public function assertDispatchedOnce(string $signal): void
    {
        $this->assertDispatchedTimes($signal, 1);
    }

    public function assertNotDispatched(string $signal): void
    {
        $this->assertDispatchedTimes($signal, 0);
    }
}
