<?php

namespace Venusian\Serve\Tests\Fixtures;

/** Waits on the event loop for one turn, so other request fibers run, as an awaited query would. */
final class LoopPause
{
    public static function yield(): void
    {
        $loop = app('event-loop');
        $promise = $loop->promise();
        $loop->at(0.0, fn () => $promise->resolve(null));
        $loop->await($promise);
    }
}
