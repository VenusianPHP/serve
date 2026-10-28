<?php

namespace Mariner\Session;

use Voyager\Contracts\IOPools\Promise;

/**
 * A session handler's async variant: the same four operations, each answered with a promise.
 *
 * Obtained explicitly (FileSessionHandler::via(), DatabaseSessionHandler::via(),
 * CacheBasedSessionHandler::async()); the handler's own methods stay blocking. A blocking call made
 * after an async one on the same session waits for it, through the framework's path, connection
 * and cache-key ordering.
 */
interface AsyncSessionHandler
{
    /** Settles with the session's raw data, or '' when there is none. */
    public function read(string $sessionId): Promise;

    /** Settles with true once written. */
    public function write(string $sessionId, string $data): Promise;

    /** Settles with true once destroyed. */
    public function destroy(string $sessionId): Promise;

    /** Settles with the number of sessions removed. */
    public function gc(int $lifetime): Promise;
}
