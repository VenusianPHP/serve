<?php

namespace Mariner\Auth;

/**
 * The guards resolved for the current request.
 *
 * Registered with scoped(), so each request (and each concurrent request fiber) resolves its own
 * guards, with their own request, session and user, while the AuthManager singleton keeps the
 * configuration registered at boot (custom guard and provider creators, the user resolver).
 */
final class GuardState
{
    /** @var array<string, \Mariner\Contracts\Auth\Guard|\Mariner\Contracts\Auth\StatefulGuard> */
    public array $guards = [];
}
