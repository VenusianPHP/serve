<?php

namespace Mariner\Contracts\Server;

use Closure;
use LogicException;
use RuntimeException;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/** An HTTP/1.1 server attached to a loop it is given and never owns. */
interface HttpServer
{
    /**
     * Bind the listening socket.
     *
     * @throws RuntimeException  the address can't be listened on
     */
    public function listen(): static;

    /** The port listened on: the one the system picked when the options asked for 0. */
    public function port(): ?int;

    /**
     * Serve on $loop, from its next turn.
     *
     * @throws LogicException  the server is already attached to a loop
     */
    public function attach(Loop $loop): static;

    /**
     * Tell $observer about each request once it is answered. The observer receives an object
     * with the request's `method`, `path`, `status` (0 when it was refused before an answer was
     * made) and `seconds`.
     *
     * @param  Closure(object): void  $observer
     */
    public function onRequest(Closure $observer): static;

    /**
     * Stop accepting connections, close the idle ones, and leave the loop once the requests in
     * flight are answered. drain() is the same, as a promise with a deadline.
     */
    public function stop(): void;

    /**
     * stop(), then wait up to $timeout_s for the requests in flight; whatever is left then closes.
     *
     * @return Promise<null>  settled once the server is off the loop
     *
     * @throws LogicException  the server is not attached to a loop
     */
    public function drain(float $timeout_s = 30.0): Promise;
}
