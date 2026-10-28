<?php

namespace Mariner\Auth\Passwords;

use Mariner\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Voyager\Contracts\Hashing\Hasher as HasherContract;
use Voyager\Database\ConnectionInterface;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Str;

class DatabaseTokenRepository implements TokenRepositoryInterface
{
    /**
     * Create a new token repository instance.
     *
     * @param  int  $expires  The number of seconds a token should remain valid.
     * @param  int  $throttle  Minimum number of seconds before the user can generate new password reset tokens.
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected HasherContract $hasher,
        protected string $table,
        #[\SensitiveParameter]
        protected string $hashKey,
        protected int $expires = 3600,
        protected int $throttle = 60,
    ) {
    }

    /**
     * Create a new token record.
     *
     * @param  \Mariner\Contracts\Auth\CanResetPassword  $user
     * @return string
     */
    public function create(CanResetPasswordContract $user)
    {
        $email = $user->getEmailForPasswordReset();

        $this->deleteExisting($user);

        // We will create a new, random token for the user so that we can e-mail them
        // a safe link to the password reset form. Then we will insert a record in
        // the database so that we can verify the token within the actual reset.
        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($email, $token));

        return $token;
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param  \Mariner\Contracts\Auth\CanResetPassword  $user
     * @return int
     */
    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->getTable()->where('email', $user->getEmailForPasswordReset())->delete();
    }

    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array
     */
    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        return ['email' => $email, 'token' => $this->hasher->make($token), 'created_at' => new Carbon];
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param  \Mariner\Contracts\Auth\CanResetPassword  $user
     * @param  string  $token
     * @return bool
     */
    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->getTable()->where(
            'email', $user->getEmailForPasswordReset()
        )->first();

        return $record &&
               ! $this->tokenExpired($record['created_at']) &&
                 $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the token has expired.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function tokenExpired($createdAt)
    {
        return Carbon::parse($createdAt)->addSeconds($this->expires)->isPast();
    }

    /**
     * Determine if the given user recently created a password reset token.
     *
     * @param  \Mariner\Contracts\Auth\CanResetPassword  $user
     * @return bool
     */
    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->getTable()->where(
            'email', $user->getEmailForPasswordReset()
        )->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * Determine if the token was recently created.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function tokenRecentlyCreated($createdAt)
    {
        if ($this->throttle <= 0) {
            return false;
        }

        return Carbon::parse($createdAt)->addSeconds(
            $this->throttle
        )->isFuture();
    }

    /**
     * Delete a token record by user.
     *
     * @param  \Mariner\Contracts\Auth\CanResetPassword  $user
     * @return void
     */
    public function delete(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);
    }

    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function deleteExpired()
    {
        $expiredAt = Carbon::now()->subSeconds($this->expires);

        $this->getTable()->where('created_at', '<', $expiredAt)->delete();
    }

    /**
     * Create a new token for the user.
     *
     * @return string
     */
    public function createNewToken()
    {
        return hash_hmac('sha256', Str::random(40), $this->hashKey);
    }

    /**
     * Get the database connection instance.
     *
     * @return \Voyager\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Begin a new database query against the table.
     *
     * @return \Voyager\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Get the hasher instance.
     *
     * @return \Voyager\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        return $this->hasher;
    }

    /**
     * The async variant: reads and writes offloaded through the connection's lanes, which run the
     * writes in call order, so create() replaces the previous token before inserting the new one.
     * 'thread', 'process' or null for the framework's pool rule.
     */
    public function via(?string $pool = null): AsyncTokenRepository
    {
        $record = fn (CanResetPasswordContract $user) => $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())->via($pool)->first()
            ->then(fn ($row) => (array) $row);

        return new AsyncTokenRepositoryCalls(
            create: function (CanResetPasswordContract $user) use ($pool) {
                $email = $user->getEmailForPasswordReset();
                $token = $this->createNewToken();

                $this->getTable()->where('email', $email)->via($pool)->delete();

                return $this->getTable()->via($pool)->insert($this->getPayload($email, $token))->then(fn () => $token);
            },
            exists: fn (CanResetPasswordContract $user, string $token) => $record($user)->then(fn (array $row) => $row
                && ! $this->tokenExpired($row['created_at'])
                && $this->hasher->check($token, $row['token'])),
            recentlyCreatedToken: fn (CanResetPasswordContract $user) => $record($user)
                ->then(fn (array $row) => $row && $this->tokenRecentlyCreated($row['created_at'])),
            delete: fn (CanResetPasswordContract $user) => $this->getTable()
                ->where('email', $user->getEmailForPasswordReset())->via($pool)->delete(),
            deleteExpired: fn () => $this->getTable()
                ->where('created_at', '<', Carbon::now()->subSeconds($this->expires))->via($pool)->delete(),
        );
    }
}
