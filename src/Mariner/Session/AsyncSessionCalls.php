<?php

namespace Mariner\Session;

use Closure;
use SessionHandlerInterface;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/**
 * An async session handler made of closures, so each handler builds its variant from its own
 * (protected) logic without repeating it.
 */
final readonly class AsyncSessionCalls implements AsyncSessionHandler
{
    public function __construct(
        private Closure $read,
        private Closure $write,
        private Closure $destroy,
        private Closure $gc,
    ) {}

    /**
     * For handlers with no I/O to wait on (array, null, cookie): runs each call in place and answers
     * with a promise already settled, as the framework's array cache store does.
     */
    public static function settled(SessionHandlerInterface $handler, Loop $loop): self
    {
        $settle = function (mixed $value) use ($loop): Promise {
            $promise = $loop->promise();
            $promise->resolve($value);

            return $promise;
        };

        return new self(
            fn (string $id) => $settle($handler->read($id)),
            fn (string $id, string $data) => $settle($handler->write($id, $data)),
            fn (string $id) => $settle($handler->destroy($id)),
            fn (int $lifetime) => $settle($handler->gc($lifetime)),
        );
    }

    public function read(string $sessionId): Promise
    {
        return ($this->read)($sessionId);
    }

    public function write(string $sessionId, string $data): Promise
    {
        return ($this->write)($sessionId, $data);
    }

    public function destroy(string $sessionId): Promise
    {
        return ($this->destroy)($sessionId);
    }

    public function gc(int $lifetime): Promise
    {
        return ($this->gc)($lifetime);
    }
}
