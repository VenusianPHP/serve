<?php

namespace Venusian\Serve\Tests\Fixtures;

use Fiber;
use Mariner\NutsAndBolts\Facades\Route;

/** Suspends mid-action, so another request runs before this one finishes. */
class FiberController
{
    public ?string $seen = null;

    public function show(string $id): array
    {
        $this->seen = $id;

        Fiber::suspend();

        return [
            'argument' => $id,
            'parameter' => Route::current()->parameter('id'),
            'path' => request()->path(),
            'controller_saw' => $this->seen,
            'controller' => spl_object_id($this),
        ];
    }
}
