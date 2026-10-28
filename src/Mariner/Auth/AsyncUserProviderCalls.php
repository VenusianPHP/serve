<?php

namespace Mariner\Auth;

use Closure;
use Mariner\Contracts\Auth\Authenticatable as UserContract;
use Voyager\Contracts\IOPools\Promise;

/**
 * An async user provider made of closures, so each provider builds its variant from its own logic.
 */
final readonly class AsyncUserProviderCalls implements AsyncUserProvider
{
    public function __construct(
        private Closure $retrieveById,
        private Closure $retrieveByToken,
        private Closure $retrieveByCredentials,
        private Closure $updateRememberToken,
        private Closure $rehashPasswordIfRequired,
    ) {}

    public function retrieveById(mixed $identifier): Promise
    {
        return ($this->retrieveById)($identifier);
    }

    public function retrieveByToken(mixed $identifier, #[\SensitiveParameter] string $token): Promise
    {
        return ($this->retrieveByToken)($identifier, $token);
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): Promise
    {
        return ($this->retrieveByCredentials)($credentials);
    }

    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] string $token): Promise
    {
        return ($this->updateRememberToken)($user, $token);
    }

    public function rehashPasswordIfRequired(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force = false): Promise
    {
        return ($this->rehashPasswordIfRequired)($user, $credentials, $force);
    }
}
