<?php

namespace Mariner\Session;

use Closure;
use Voyager\Contracts\IOPools\Promise;

/**
 * A session store's async variant (Store::async()): starting and saving are the only I/O a store
 * does, and each is answered with a promise. Everything else stays on the store itself.
 */
final readonly class AsyncStore
{
    public function __construct(
        private Closure $start,
        private Closure $save,
    ) {}

    /** Settles with true once the session's data is loaded. */
    public function start(): Promise
    {
        return ($this->start)();
    }

    /** Settles once the session's data is written. */
    public function save(): Promise
    {
        return ($this->save)();
    }
}
