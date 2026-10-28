<?php

namespace Mariner\Auth;

use Mariner\Contracts\Auth\Authenticatable as UserContract;
use Voyager\Contracts\IOPools\Promise;

/**
 * A user provider's async variant: its lookups and writes, each answered with a promise.
 *
 * Obtained explicitly from DatabaseUserProvider::via(); the provider's own
 * methods stay blocking. Checking a password (validateCredentials) is hashing, not I/O, so it stays
 * on the provider.
 */
interface AsyncUserProvider extends AsyncUserLookup
{
    /** Settles once the user's remember token is stored. */
    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] string $token): Promise;

    /** Settles once the password is rehashed, or at once when it needs no rehash. */
    public function rehashPasswordIfRequired(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force = false): Promise;
}
