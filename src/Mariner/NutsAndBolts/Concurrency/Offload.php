<?php

namespace Mariner\NutsAndBolts\Concurrency;

use InvalidArgumentException;
use Throwable;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkerPools\RemoteException;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;
use Voyager\Contracts\Vessel\TheServiceContainer;

/**
 * Hands work to Venusian's worker pools, the way the framework's own async variants do.
 */
final class Offload
{
    /**
     * Run a gig on a worker pool: 'thread' or 'process' by name, or by the framework's rule when
     * null (thread workers when bound, else process workers).
     */
    public static function submit(TheServiceContainer $app, ShouldPool $gig, ?string $pool = null): Promise
    {
        $key = match ($pool) {
            'thread' => 'thread-workers',
            'process' => 'process-workers',
            null => $app->isBound('thread-workers') ? 'thread-workers' : 'process-workers',
            default => throw new InvalidArgumentException("Unknown worker pool [{$pool}]: use 'thread', 'process' or null."),
        };

        if (! $app->isBound($key)) {
            throw new InvalidArgumentException("No [{$key}] pool is bound: enable it under io-pools.pool_workers.");
        }

        return $app->make($key)->submit($gig);
    }

    /**
     * Whether $e is $class, thrown here or inside a worker, where only its class name crosses back.
     *
     * @param  class-string<Throwable>  $class
     */
    public static function isRemote(Throwable $e, string $class): bool
    {
        return $e instanceof $class || ($e instanceof RemoteException && is_a($e->remote_class, $class, true));
    }
}
