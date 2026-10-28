<?php

namespace Mariner\Auth;

use Closure;
use Mariner\Contracts\Auth\Authenticatable as UserContract;
use Mariner\Contracts\Auth\UserProvider;
use Voyager\Contracts\Hashing\Hasher as HasherContract;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\Query\Builder;

class DatabaseUserProvider implements UserProvider
{
    /**
     * The active database connection.
     *
     * @var \Voyager\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * The hasher implementation.
     *
     * @var \Voyager\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The table containing the users.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param  \Voyager\Database\ConnectionInterface  $connection
     * @param  \Voyager\Contracts\Hashing\Hasher  $hasher
     * @param  string  $table
     */
    public function __construct(ConnectionInterface $connection, HasherContract $hasher, $table)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Mariner\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->connection->table($this->table)->find($identifier);

        return $this->getGenericUser($user);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Mariner\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return $this->userWithToken($this->connection->table($this->table)->find($identifier), $token);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Mariner\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] $token)
    {
        $this->connection->table($this->table)
            ->where($user->getAuthIdentifierName(), $user->getAuthIdentifier())
            ->update([$user->getRememberTokenName() => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Mariner\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        if (is_null($query = $this->credentialsQuery($credentials))) {
            return null;
        }

        // Now we are ready to execute the query to see if we have a user matching
        // the given credentials. If not, we will just return null and indicate
        // that there are no matching users from the given credential arrays.
        return $this->getGenericUser($query->first());
    }

    /**
     * Get the generic user.
     *
     * @param  mixed  $user
     * @return \Mariner\Auth\GenericUser|null
     */
    protected function getGenericUser($user)
    {
        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }

        return null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Mariner\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, #[\SensitiveParameter] array $credentials)
    {
        if (is_null($plain = $credentials['password'])) {
            return false;
        }

        if (is_null($hashed = $user->getAuthPassword())) {
            return false;
        }

        return $this->hasher->check($plain, $hashed);
    }

    /**
     * Rehash the user's password if required and supported.
     *
     * @param  \Mariner\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @param  bool  $force
     * @return void
     */
    public function rehashPasswordIfRequired(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        if (! is_null($rehash = $this->passwordRehash($user, $credentials, $force))) {
            $rehash['query']->update($rehash['values']);
        }
    }

    /**
     * The user a fetched row makes, when its remember token matches.
     *
     * @param  object|array|null  $row
     */
    protected function userWithToken($row, #[\SensitiveParameter] $token): ?GenericUser
    {
        $user = $this->getGenericUser($row);

        return $user && $user->getRememberToken() && hash_equals($user->getRememberToken(), $token)
            ? $user
            : null;
    }

    /**
     * The query that finds a user by credentials (password aside), or null when none are left.
     */
    protected function credentialsQuery(#[\SensitiveParameter] array $credentials): ?Builder
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        // First we will add each credential element to the query as a where clause.
        $query = $this->connection->table($this->table);

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        return $query;
    }

    /**
     * The update that rehashes the user's password, or null when it needs none.
     *
     * @return array{query: \Voyager\Database\Query\Builder, values: array}|null
     */
    protected function passwordRehash(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force): ?array
    {
        if (! $this->hasher->needsRehash($user->getAuthPassword()) && ! $force) {
            return null;
        }

        return [
            'query' => $this->connection->table($this->table)->where($user->getAuthIdentifierName(), $user->getAuthIdentifier()),
            'values' => [$user->getAuthPasswordName() => $this->hasher->make($credentials['password'])],
        ];
    }

    /**
     * The async variant: every query offloaded through the connection's lanes, so a blocking query
     * on this connection made afterwards waits for it. 'thread', 'process' or null for the
     * framework's pool rule. Hashing stays here: it's CPU, not I/O.
     */
    public function via(?string $pool = null): AsyncUserProvider
    {
        $loop = app('event-loop');
        $settled = function (mixed $value) use ($loop) {
            $promise = $loop->promise();
            $promise->resolve($value);

            return $promise;
        };

        return new AsyncUserProviderCalls(
            retrieveById: fn ($id) => $this->connection->table($this->table)->via($pool)->find($id)
                ->then(fn ($row) => $this->getGenericUser($row)),
            retrieveByToken: fn ($id, $token) => $this->connection->table($this->table)->via($pool)->find($id)
                ->then(fn ($row) => $this->userWithToken($row, $token)),
            retrieveByCredentials: fn (array $credentials) => is_null($query = $this->credentialsQuery($credentials))
                ? $settled(null)
                : $query->via($pool)->first()->then(fn ($row) => $this->getGenericUser($row)),
            updateRememberToken: fn (UserContract $user, $token) => $this->connection->table($this->table)
                ->where($user->getAuthIdentifierName(), $user->getAuthIdentifier())
                ->via($pool)->update([$user->getRememberTokenName() => $token]),
            rehashPasswordIfRequired: fn (UserContract $user, array $credentials, bool $force = false) => is_null($rehash = $this->passwordRehash($user, $credentials, $force))
                ? $settled(null)
                : $rehash['query']->via($pool)->update($rehash['values']),
        );
    }
}
