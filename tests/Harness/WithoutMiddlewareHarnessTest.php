<?php

namespace Venusian\Serve\Tests\Harness;

use Mariner\Core\Testing\TestCase;
use Mariner\Core\Testing\WithoutMiddleware;

/** WithoutMiddleware switches middleware off before every test of the class. */
class WithoutMiddlewareHarnessTest extends TestCase
{
    use BootsHarnessApp, WithoutMiddleware;

    public function test_middleware_is_off_for_the_whole_class(): void
    {
        $this->get('/hello')->assertOk()->assertCookieMissing(config('session.cookie'));
    }
}
