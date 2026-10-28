<?php

namespace Mariner\Auth;

use Voyager\Contracts\IOPools\Promise;

/**
 * The lookups of a user provider's async variant, each answered with a promise.
 *
 * InstrumentUserProvider::via() offers these only: its writes save the model, which runs model events
 * and timestamps, and Venusian offloads queries, not model saves, so those stay blocking.
 */
interface AsyncUserLookup
{
    /** Settles with the user, or null. */
    public function retrieveById(mixed $identifier): Promise;

    /** Settles with the user whose remember token matches, or null. */
    public function retrieveByToken(mixed $identifier, #[\SensitiveParameter] string $token): Promise;

    /** Settles with the user the credentials (password aside) find, or null. */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): Promise;
}
