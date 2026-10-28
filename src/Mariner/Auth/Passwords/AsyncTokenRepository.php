<?php

namespace Mariner\Auth\Passwords;

use Mariner\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Voyager\Contracts\IOPools\Promise;

/**
 * A password reset token repository's async variant, each operation answered with a promise.
 *
 * Obtained explicitly (DatabaseTokenRepository::via(), CacheTokenRepository::async()); the
 * repository's own methods stay blocking. Hashing and expiry checks run here, in the caller; only
 * the reads and writes are async, ordered by the framework's connection lanes or cache keys.
 */
interface AsyncTokenRepository
{
    /** Settles with the new plain-text token, once any previous one is replaced. */
    public function create(CanResetPasswordContract $user): Promise;

    /** Settles with whether the token is the user's, unexpired. */
    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] string $token): Promise;

    /** Settles with whether the user got a token within the throttle window. */
    public function recentlyCreatedToken(CanResetPasswordContract $user): Promise;

    /** Settles once the user's token is gone. */
    public function delete(CanResetPasswordContract $user): Promise;

    /** Settles once expired tokens are gone. */
    public function deleteExpired(): Promise;
}
