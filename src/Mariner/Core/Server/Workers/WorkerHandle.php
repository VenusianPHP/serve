<?php

namespace Mariner\Core\Server\Workers;

/** One worker the Supervisor keeps running: a process or a thread. */
interface WorkerHandle
{
    public function running(): bool;

    /** How it ended, once it has: "status 3", "signal 9", or an exception's class and message. */
    public function ended(): string;

    /** Ask it to answer what it has in flight and stop; $signal for a process, ignored by a thread. */
    public function stop(int $signal): void;

    /** End it now. */
    public function kill(): void;

    /** Release what it holds, once it has ended. */
    public function release(): void;
}
