<?php

namespace Mariner\Auth;

use Closure;
use Mariner\Contracts\Auth\Authenticatable as UserContract;
use Mariner\Contracts\Auth\UserProvider;
use Voyager\Contracts\Hashing\Hasher as HasherContract;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Database\Instrument\Builder;

class InstrumentUserProvider implements UserProvider
{
    /**
     * The hasher implementation.
     *
     * @var \Voyager\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The Instrument user model.
     *
     * @var class-string<\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model>
     */
    protected $model;

    /**
     * The callback that may modify the user retrieval queries.
     *
     * @var (\Closure(\Voyager\Database\Instrument\Builder<*>):mixed)|null
     */
    protected $queryCallback;

    /**
     * Create a new database user provider.
     *
     * @param  \Voyager\Contracts\Hashing\Hasher  $hasher
     * @param  string  $model
     */
    public function __construct(HasherContract $hasher, $model)
    {
        $this->model = $model;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return (\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model)|null
     */
    public function retrieveById($identifier)
    {
        return $this->idQuery($identifier)->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return (\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model)|null
     */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return $this->modelWithToken($this->idQuery($identifier)->first(), $token);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] $token)
    {
        $user->setRememberToken($token);

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return (\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model)|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        return $this->credentialsQuery($credentials)?->first();
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
     * @param  \Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model  $user
     * @param  array  $credentials
     * @param  bool  $force
     * @return void
     */
    public function rehashPasswordIfRequired(UserContract $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        if (! $this->hasher->needsRehash($user->getAuthPassword()) && ! $force) {
            return;
        }

        $user->forceFill([
            $user->getAuthPasswordName() => $this->hasher->make($credentials['password']),
        ])->save();
    }

    /**
     * Get a new query builder for the model instance.
     *
     * @template TModel of \Voyager\Database\Instrument\Model
     *
     * @param  TModel|null  $model
     * @return \Voyager\Database\Instrument\Builder<TModel>
     */
    protected function newModelQuery($model = null)
    {
        $query = is_null($model)
            ? $this->createModel()->newQuery()
            : $model->newQuery();

        with($query, $this->queryCallback);

        return $query;
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the hasher implementation.
     *
     * @return \Voyager\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        return $this->hasher;
    }

    /**
     * Sets the hasher implementation.
     *
     * @param  \Voyager\Contracts\Hashing\Hasher  $hasher
     * @return $this
     */
    public function setHasher(HasherContract $hasher)
    {
        $this->hasher = $hasher;

        return $this;
    }

    /**
     * Gets the name of the Instrument user model.
     *
     * @return class-string<\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model>
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Sets the name of the Instrument user model.
     *
     * @param  class-string<\Mariner\Contracts\Auth\Authenticatable&\Voyager\Database\Instrument\Model>  $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the callback that modifies the query before retrieving users.
     *
     * @return (\Closure(\Voyager\Database\Instrument\Builder<*>):mixed)|null
     */
    public function getQueryCallback()
    {
        return $this->queryCallback;
    }

    /**
     * Sets the callback to modify the query before retrieving users.
     *
     * @param  (\Closure(\Voyager\Database\Instrument\Builder<*>):mixed)|null  $queryCallback
     * @return $this
     */
    public function withQuery($queryCallback = null)
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * The query that finds a user by identifier.
     */
    protected function idQuery(mixed $identifier): Builder
    {
        $model = $this->createModel();

        return $this->newModelQuery($model)->where($model->getAuthIdentifierName(), $identifier);
    }

    /**
     * The retrieved user, when its remember token matches.
     */
    protected function modelWithToken(?UserContract $retrievedModel, #[\SensitiveParameter] $token): ?UserContract
    {
        if (! $retrievedModel) {
            return null;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $retrievedModel : null;
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
        $query = $this->newModelQuery();

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
     * The async variant: each lookup query offloaded through the connection's lanes, models returned
     * as the blocking lookups return them; the two writes save the user with the model's own
     * saveAsync(), so its events and timestamps run here and only the SQL runs in a worker.
     * 'thread', 'process' or null for the framework's pool rule.
     */
    public function via(?string $pool = null): AsyncUserProvider
    {
        $loop = app('event-loop');

        return new AsyncUserProviderCalls(
            retrieveById: fn ($id) => $this->idQuery($id)->via($pool)->first(),
            retrieveByToken: fn ($id, $token) => $this->idQuery($id)->via($pool)->first()->then(fn ($user) => $this->modelWithToken($user, $token)),
            retrieveByCredentials: function (array $credentials) use ($pool, $loop) {
                if (! is_null($query = $this->credentialsQuery($credentials))) {
                    return $query->via($pool)->first();
                }

                $none = $loop->promise();
                $none->resolve(null);

                return $none;
            },
            // The model's own saveAsync(): its lifecycle runs here, only the SQL in a worker.
            updateRememberToken: fn (UserContract $user, string $token) => $loop->async(function () use ($user, $token, $pool) {
                $user->setRememberToken($token);

                $timestamps = $user->timestamps;
                $user->timestamps = false;

                try {
                    $user->saveAsync($pool)->wait();
                } finally {
                    $user->timestamps = $timestamps;
                }
            }),
            rehashPasswordIfRequired: fn (UserContract $user, array $credentials, bool $force = false) => $loop->async(function () use ($user, $credentials, $force, $pool) {
                if (! $this->hasher->needsRehash($user->getAuthPassword()) && ! $force) {
                    return;
                }

                $user->forceFill([
                    $user->getAuthPasswordName() => $this->hasher->make($credentials['password']),
                ])->saveAsync($pool)->wait();
            }),
        );
    }
}
