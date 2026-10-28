<?php

namespace Venusian\Serve\Tests\Fixtures;

use Closure;
use DateInterval;
use DateTimeInterface;
use Voyager\NutsAndBolts\Collection;
use Voyager\Contracts\Queue\Factory;
use Voyager\Contracts\Queue\Job;
use Voyager\Contracts\Queue\Queue;

/**
 * A queue factory and queue in one that records what is pushed, for the assertions Laravel's QueueFake gave.
 */
class QueueRecorder implements Factory, Queue
{
    /** @var list<array{queue: ?string, job: object|string, delay: mixed}> */
    private array $pushed = [];

    private string $connectionName = 'recorder';

    public function connection(?string $name = null): Queue
    {
        return $this;
    }

    public function size(?string $queue = null): int
    {
        return count($this->pushed);
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->record($queue, $job, null);
    }

    public function pushOn(string $queue, object|string $job, mixed $data = ''): mixed
    {
        return $this->record($queue, $job, null);
    }

    public function pushRaw(string $payload, ?string $queue = null, array $options = []): mixed
    {
        return $this->record($queue, $payload, null);
    }

    public function later(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->record($queue, $job, $delay);
    }

    public function laterOn(string $queue, DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = ''): mixed
    {
        return $this->record($queue, $job, $delay);
    }

    public function bulk(array $jobs, mixed $data = '', ?string $queue = null): mixed
    {
        foreach ($jobs as $job) {
            $this->record($queue, $job, null);
        }

        return null;
    }

    public function pop(?string $queue = null): ?Job
    {
        return null;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function setConnectionName(string $name): static
    {
        $this->connectionName = $name;

        return $this;
    }

    /** The pushed jobs of a class. */
    public function pushed(string $class): Collection
    {
        return new Collection(array_values(array_map(fn ($p) => $p['job'], array_filter($this->pushed, fn ($p) => $p['job'] instanceof $class))));
    }

    public function assertNothingPushed(): void
    {
        expect($this->pushed)->toBeEmpty();
    }

    public function assertPushedOn(?string $queue, string $class): void
    {
        expect(array_filter($this->pushed, fn ($p) => $p['queue'] === $queue && $p['job'] instanceof $class))->not->toBeEmpty();
    }

    public function assertPushed(string $class, ?Closure $callback = null): void
    {
        $matching = array_filter($this->pushed, fn ($p) => $p['job'] instanceof $class && (! $callback || $callback($p['job'], $p['queue'])));

        expect($matching)->not->toBeEmpty();
    }

    private function record(?string $queue, object|string $job, mixed $delay): mixed
    {
        $this->pushed[] = ['queue' => $queue, 'job' => $job, 'delay' => $delay];

        return null;
    }
}
