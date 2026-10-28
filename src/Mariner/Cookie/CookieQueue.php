<?php

namespace Mariner\Cookie;

/**
 * The cookies queued for the current response, by name then path.
 *
 * Registered with scoped(), so a cookie one request queues is only ever sent with that request's
 * response, never with a concurrent request's.
 */
final class CookieQueue
{
    /** @var array<string, array<string, \Symfony\Component\HttpFoundation\Cookie>> */
    public array $cookies = [];
}
