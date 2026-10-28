<?php

namespace Mariner\Core\Server\Workers;

use parallel\Channel;
use parallel\Future;
use parallel\Runtime;
use Throwable;

/**
 * A worker as a thread of this process (a parallel\Runtime): it boots the app, serves on its own
 * loop, and stops when told to over a channel, after answering what it has in flight. A thread
 * can't be signalled, so stop() is that message; kill() is for one still running past the drain.
 *
 * A PHP fatal error inside a thread ends the whole process: ext-parallel can't contain it. Process
 * workers can.
 */
final class ThreadWorker implements WorkerHandle
{
    private Runtime $runtime;

    private Future $future;

    private Channel $control;

    private ?string $ended = null;

    /**
     * @param  array<string, mixed>  $options  the ServerOptions the thread serves with, as plain values
     */
    public function __construct(string $autoload, string $bootstrap, int $index, array $options, float $drain_timeout)
    {
        $this->control = Channel::make('serve.worker.'.$index.'.'.bin2hex(random_bytes(4)), Channel::Infinite);
        $this->runtime = new Runtime($autoload);
        $this->future = $this->runtime->run(
            static function (string $bootstrap, int $index, array $options, float $drain_timeout, Channel $control): int {
                return \Mariner\Core\Server\Workers\ThreadServe::run($bootstrap, $index, $options, $drain_timeout, $control);
            },
            [$bootstrap, $index, $options, $drain_timeout, $this->control],
        );
    }

    public function running(): bool
    {
        if (! is_null($this->ended)) {
            return false;
        }

        if (! $this->future->done()) {
            return true;
        }

        try {
            $this->ended = 'status '.(int) $this->future->value();
        } catch (Throwable $e) {
            $this->ended = $e::class.': '.$e->getMessage();
        }

        return false;
    }

    public function ended(): string
    {
        return $this->ended ?? 'still running';
    }

    public function stop(int $signal): void
    {
        $this->control->send('stop');
    }

    public function kill(): void
    {
        $this->runtime->kill();
    }

    public function release(): void
    {
        try {
            $this->control->close();
        } catch (Throwable) {
            // already closed
        }
    }
}
