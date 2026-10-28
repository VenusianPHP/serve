<?php

namespace Mariner\Core\Server\Workers;

use RuntimeException;

/** A worker as a child process with the console's own output; stopped by signal. */
final class ProcessWorker implements WorkerHandle
{
    /** @var resource */
    private $process;

    /** @var array{running: bool, signaled: bool, termsig: int, exitcode: int}|null */
    private ?array $final = null;

    /** @param list<string> $command */
    public function __construct(array $command)
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('A worker process could not be started: '.implode(' ', $command));
        }

        $this->process = $process;
    }

    public function running(): bool
    {
        if (! is_null($this->final)) {
            return false;
        }

        $status = proc_get_status($this->process);

        if ($status['running']) {
            return true;
        }

        $this->final = $status;

        return false;
    }

    public function ended(): string
    {
        return is_null($this->final) ? 'still running' : ($this->final['signaled'] ? 'signal '.$this->final['termsig'] : 'status '.$this->final['exitcode']);
    }

    public function stop(int $signal): void
    {
        proc_terminate($this->process, $signal);
    }

    public function kill(): void
    {
        proc_terminate($this->process, SIGKILL);
    }

    public function release(): void
    {
        proc_close($this->process);
    }
}
