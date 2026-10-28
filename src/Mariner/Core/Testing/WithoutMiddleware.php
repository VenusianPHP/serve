<?php

namespace Mariner\Core\Testing;

use Exception;

trait WithoutMiddleware
{
    /**
     * Run before each test by the TestCase.
     */
    public function setUpWithoutMiddleware(): void
    {
        $this->disableMiddlewareForAllTests();
    }

    /**
     * Prevent all middleware from being executed for this test class.
     *
     * @throws \Exception
     */
    public function disableMiddlewareForAllTests()
    {
        if (method_exists($this, 'withoutMiddleware')) {
            $this->withoutMiddleware();
        } else {
            throw new Exception('Unable to disable middleware. MakesHttpRequests trait not used.');
        }
    }
}
