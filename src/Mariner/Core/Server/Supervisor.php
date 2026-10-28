<?php

namespace Mariner\Core\Server;

use Closure;
use Mariner\Core\Server\Workers\WorkerHandle;

/**
 * Keeps N workers running (processes or threads, through WorkerHandle): restarted with a growing
 * pause when one dies, and stopped together when this process is told to stop. A worker that
 * keeps dying is given up on: after max_restarts within window seconds the rest are stopped and
 * run() returns 1, saying why, rather than restart it forever.
 */
final class Supervisor
{
    /** @var array<int, WorkerHandle> running workers, by index */
    private array $children = [];

    /** @var array<int, list<float>> when each index's child died, within the window */
    private array $deaths = [];

    /** @var array<int, float> when each dead index may start again */
    private array $restart_at = [];

    private ?int $stop_signal = null;

    private ?string $gave_up = null;

    /**
     * @param  Closure(int): WorkerHandle  $start  starts worker $index
     * @param  Closure(string): void  $say  where the supervisor's own messages go
     */
    public function __construct(
        private readonly Closure $start,
        private readonly int $workers,
        private readonly Closure $say,
        private readonly int $max_restarts = 5,
        private readonly float $window = 60.0,
        private readonly float $max_backoff = 30.0,
        private readonly float $stop_timeout = 35.0,
    ) {}

    /**
     * Run until stopped by SIGINT or SIGTERM (forwarded to the children), or until a child keeps
     * dying.
     *
     * @return int  0 once stopped, 1 after giving up on a child
     */
    public function run(): int
    {
        $this->listenForStop();

        for ($index = 0; $index < $this->workers; $index++) {
            $this->start($index);
        }

        while (is_null($this->stop_signal) && is_null($this->gave_up)) {
            $this->reap();
            $this->restartDue();
            usleep(50_000);
        }

        $this->stopAll($this->stop_signal ?? SIGTERM);
        $this->forgetStop();

        if (! is_null($this->gave_up)) {
            ($this->say)($this->gave_up);

            return 1;
        }

        return 0;
    }

    /** Ask the supervisor to stop its children with $signal, as SIGINT or SIGTERM would. */
    public function stop(int $signal = SIGTERM): void
    {
        $this->stop_signal ??= $signal;
    }

    private function start(int $index): void
    {
        $this->children[$index] = ($this->start)($index);
    }

    /** Notice children that died, and schedule their restart or give up on them. */
    private function reap(): void
    {
        foreach ($this->children as $index => $child) {
            if ($child->running()) {
                continue;
            }

            $exit = $child->ended();
            $child->release();
            unset($this->children[$index]);

            $now = microtime(true);
            $recent = array_values(array_filter($this->deaths[$index] ?? [], fn (float $at) => $now - $at < $this->window));
            $recent[] = $now;
            $this->deaths[$index] = $recent;

            if (count($recent) > $this->max_restarts) {
                $this->gave_up = sprintf(
                    'Worker %d died %d times within %ds (last with %s): giving up and stopping the others.',
                    $index, count($recent), (int) $this->window, $exit,
                );

                return;
            }

            $pause = min($this->max_backoff, 2 ** (count($recent) - 1) * 0.5);
            $this->restart_at[$index] = $now + $pause;
            ($this->say)(sprintf('Worker %d exited with %s; restarting in %.1fs.', $index, $exit, $pause));
        }
    }

    private function restartDue(): void
    {
        foreach ($this->restart_at as $index => $at) {
            if (microtime(true) >= $at) {
                unset($this->restart_at[$index]);
                $this->start($index);
            }
        }
    }

    /** Ask every worker to stop and wait for them; any still running at the deadline is killed. */
    private function stopAll(int $signal): void
    {
        foreach ($this->children as $child) {
            $child->stop($signal);
        }

        $deadline = microtime(true) + $this->stop_timeout;

        while ($this->children !== [] && microtime(true) < $deadline) {
            foreach ($this->children as $index => $child) {
                if (! $child->running()) {
                    $child->release();
                    unset($this->children[$index]);
                }
            }

            usleep(20_000);
        }

        foreach ($this->children as $child) {
            $child->kill();
            $child->release();
        }

        $this->children = [];
        $this->restart_at = [];
    }

    private function listenForStop(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $this->stop(SIGINT));
        pcntl_signal(SIGTERM, fn () => $this->stop(SIGTERM));
    }

    private function forgetStop(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_signal(SIGTERM, SIG_DFL);
        }
    }
}
