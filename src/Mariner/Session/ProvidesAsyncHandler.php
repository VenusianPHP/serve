<?php

namespace Mariner\Session;

/**
 * A session handler with an async variant, so Store::async() can use it. Implement it on a custom
 * handler to make its I/O awaitable.
 */
interface ProvidesAsyncHandler
{
    /** 'thread', 'process' or null for the framework's pool rule, where the variant offloads. */
    public function asyncHandler(?string $pool = null): AsyncSessionHandler;
}
