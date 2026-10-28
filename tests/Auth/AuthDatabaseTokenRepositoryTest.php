<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Passwords\DatabaseTokenRepository;
use Mariner\Contracts\Auth\CanResetPassword;
use Voyager\Database\SQLiteConnection;
use Voyager\Hashing\BcryptHasher;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use PDO;

test('create inserts new record into table', function () {
    $repo = getRepo();
    $user = getUser('email');

    $token = $repo->create($user);

    expect($token)->toBeString();
    expect(strlen($token))->toBeGreaterThan(1);
    expect($repo->exists($user, $token))->toBeTrue();
});

test('exist returns false if no row found for user', function () {
    $repo = getRepo();

    expect($repo->exists(getUser('email'), 'token'))->toBeFalse();
});

test('exist returns false if record is expired', function () {
    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', Carbon::now()->subSeconds(300000));

    expect($repo->exists($user, 'token'))->toBeFalse();
});

test('exist returns true if valid record exists', function () {
    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', Carbon::now()->subMinutes(10));

    expect($repo->exists($user, 'token'))->toBeTrue();
});

test('exist returns false if invalid token', function () {
    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', Carbon::now()->subMinutes(10));

    expect($repo->exists($user, 'wrong-token'))->toBeFalse();
});

test('recently created returns false if no row found for user', function () {
    $repo = getRepo();

    expect($repo->recentlyCreatedToken(getUser('email')))->toBeFalse();
});

test('recently created returns true if record is recently created', function () {
    Carbon::setTestNow($now = Carbon::now());

    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', $now->clone()->subSeconds(59));

    expect($repo->recentlyCreatedToken($user))->toBeTrue();
});

test('recently created returns false if valid record exists', function () {
    Carbon::setTestNow($now = Carbon::now());

    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', $now->clone()->subSeconds(61));

    expect($repo->recentlyCreatedToken($user))->toBeFalse();
});

test('delete method deletes by token', function () {
    $repo = getRepo();
    $user = getUser('email');
    insertToken($repo, 'email', 'token', Carbon::now());

    $repo->delete($user);

    expect($repo->exists($user, 'token'))->toBeFalse();
});

test('delete expired method deletes expired tokens', function () {
    $repo = getRepo();
    insertToken($repo, 'expired@example.com', 'expired-token', Carbon::now()->subSeconds(3700));
    insertToken($repo, 'recent@example.com', 'recent-token', Carbon::now()->subSeconds(10));

    $repo->deleteExpired();

    $rows = $repo->getConnection()->table('table')->pluck('email')->all();
    expect($rows)->toBe(['recent@example.com']);
});

function getRepo()
{
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));

    $connection->getSchemaBuilder()->create('table', function ($table) {
        $table->string('email')->index();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    return new DatabaseTokenRepository($connection, new BcryptHasher, 'table', 'key');
}

function insertToken(DatabaseTokenRepository $repo, $email, $token, Carbon $createdAt)
{
    $repo->getConnection()->table('table')->insert([
        'email' => $email,
        'token' => $repo->getHasher()->make($token),
        'created_at' => $createdAt,
    ]);
}

function getUser($email)
{
    return new class($email) implements CanResetPassword
    {
        public function __construct(protected $email)
        {
        }

        public function getEmailForPasswordReset()
        {
            return $this->email;
        }

        public function sendPasswordResetNotification($token)
        {
            //
        }
    };
}
