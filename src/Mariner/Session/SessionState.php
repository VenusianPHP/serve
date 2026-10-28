<?php

namespace Mariner\Session;

/**
 * The session stores opened during the current request, by driver.
 *
 * Registered with scoped(), so each request (and each concurrent request fiber) reads and writes
 * its own session, while the SessionManager singleton keeps the drivers' configuration and the
 * custom creators registered at boot.
 */
final class SessionState
{
    /** @var array<string, \Mariner\Session\Store> */
    public array $drivers = [];
}
