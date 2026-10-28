<?php

namespace Mariner\Auth\Passwords;

use Closure;
use Mariner\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Voyager\Contracts\IOPools\Promise;

/**
 * An async token repository made of closures, so each repository builds its variant from its own logic.
 */
final readonly class AsyncTokenRepositoryCalls implements AsyncTokenRepository
{
    public function __construct(
        private Closure $create,
        private Closure $exists,
        private Closure $recentlyCreatedToken,
        private Closure $delete,
        private Closure $deleteExpired,
    ) {}

    public function create(CanResetPasswordContract $user): Promise
    {
        return ($this->create)($user);
    }

    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] string $token): Promise
    {
        return ($this->exists)($user, $token);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user): Promise
    {
        return ($this->recentlyCreatedToken)($user);
    }

    public function delete(CanResetPasswordContract $user): Promise
    {
        return ($this->delete)($user);
    }

    public function deleteExpired(): Promise
    {
        return ($this->deleteExpired)();
    }
}
