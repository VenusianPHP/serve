<?php

namespace Venusian\Serve\Tests\Fixtures;

/** A controller whose action must never run under Precognition. */
class PrecognitionController
{
    public static int $ran = 0;

    public function store(NameRequest $request): string
    {
        static::$ran++;

        return 'stored';
    }
}
