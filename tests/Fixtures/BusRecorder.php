<?php

namespace Venusian\Serve\Tests\Fixtures;

use Closure;
use Voyager\Contracts\Bus\Dispatcher;
use Voyager\NutsAndBolts\Collection;

/**
 * A bus that records the commands dispatched to it, for the assertions Laravel's BusFake gave.
 */
class BusRecorder implements Dispatcher
{
    /** @var list<mixed> */
    private array $commands = [];

    public function dispatch(mixed $command)
    {
        return $this->record($command);
    }

    public function dispatchSync(mixed $command, mixed $handler = null)
    {
        return $this->record($command);
    }

    public function dispatchNow(mixed $command, mixed $handler = null)
    {
        return $this->record($command);
    }

    public function hasCommandHandler(mixed $command)
    {
        return false;
    }

    public function getCommandHandler(mixed $command)
    {
        return false;
    }

    public function pipeThrough(array $pipes)
    {
        return $this;
    }

    public function map(array $map)
    {
        return $this;
    }

    /** The dispatched commands of a class, optionally those a callback accepts. */
    public function dispatched(string $command, ?Closure $callback = null): Collection
    {
        return (new Collection($this->commands))
            ->filter(fn ($c) => $c instanceof $command && (! $callback || $callback($c)))
            ->values();
    }

    public function assertDispatched(string $command, Closure|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertDispatchedTimes($command, $callback);

            return;
        }

        expect($this->dispatched($command, $callback))->not->toBeEmpty();
    }

    public function assertDispatchedTimes(string $command, int $times): void
    {
        $actual = $this->dispatched($command)->count();

        expect($actual)->toBe($times, "[{$command}] was dispatched {$actual} times, expected {$times}.");
    }

    public function assertNothingDispatched(): void
    {
        expect($this->commands)->toBeEmpty();
    }

    private function record(mixed $command)
    {
        $this->commands[] = $command;

        return null;
    }
}
