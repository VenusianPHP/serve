<?php

namespace Venusian\Serve\Tests\Fixtures;

class FixtureController
{
    public function show(string $id): string
    {
        return "user {$id}";
    }
}
