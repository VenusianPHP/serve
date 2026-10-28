<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\DatabaseUserProvider;
use Mariner\Auth\GenericUser;
use Mariner\Contracts\Auth\Authenticatable;
use Voyager\Contracts\Hashing\Hasher;
use Voyager\Database\Connection;
use Voyager\Database\SQLiteConnection;
use Voyager\Hashing\BcryptHasher;
use Mockery;
use PDO;

test('retrieve by idreturns user when user is found', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveById(3);

    expect($user)->toBeInstanceOf(GenericUser::class);
    expect($user->getAuthIdentifier())->toBe(3);
    expect($user->name)->toBe('taylor');
});

test('retrieve by idreturns null when user is not found', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveById(99);

    expect($user)->toBeNull();
});

test('retrieve by token returns user', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveByToken(3, 'a');

    expect($user)->toBeInstanceOf(GenericUser::class);
    expect($user->getAuthIdentifier())->toBe(3);
});

test('retrieve token with bad identifier returns null', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveByToken(99, 'a');

    expect($user)->toBeNull();
});

test('retrieve by bad token returns null', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveByToken(1, 'a');

    expect($user)->toBeNull();
});

test('retrieve by credentials returns user when user is found', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveByCredentials(['username' => 'dayle', 'password' => 'foo', 'group' => ['one', 'two']]);

    expect($user)->toBeInstanceOf(GenericUser::class);
    expect($user->getAuthIdentifier())->toBe(3);
    expect($user->name)->toBe('taylor');
});

test('retrieve by credentials accepts callback', function () {
    $provider = newDatabaseProvider();

    $user = $provider->retrieveByCredentials([function ($builder) {
        $builder->where('username', 'dayle');
        $builder->whereIn('group', ['one', 'two']);
    }]);

    expect($user)->toBeInstanceOf(GenericUser::class);
    expect($user->getAuthIdentifier())->toBe(3);
    expect($user->name)->toBe('taylor');
});

test('retrieve by credentials returns null when user is found', function () {
    $provider = newDatabaseProvider();
    $user = $provider->retrieveByCredentials(['username' => 'nobody']);

    expect($user)->toBeNull();
});

test('retrieve by credentials with multiply passwords returns null', function () {
    $conn = new Connection(new PDO('sqlite::memory:'));
    $hasher = new BcryptHasher;
    $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
    $user = $provider->retrieveByCredentials([
        'password' => 'dayle',
        'password2' => 'night',
    ]);

    expect($user)->toBeNull();
});

test('credential validation', function () {
    $conn = new Connection(new PDO('sqlite::memory:'));
    $hasher = new BcryptHasher;
    $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
    $user = new GenericUser(['password' => $hasher->make('plain')]);
    $result = $provider->validateCredentials($user, ['password' => 'plain']);

    expect($result)->toBeTrue();
});

test('credential validation fails', function () {
    $conn = new Connection(new PDO('sqlite::memory:'));
    $hasher = new BcryptHasher;
    $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
    $user = new GenericUser(['password' => $hasher->make('plain')]);
    $result = $provider->validateCredentials($user, ['password' => 'wrong']);

    expect($result)->toBeFalse();
});

test('credential validation fails gracefully with null password', function () {
    $conn = new Connection(new PDO('sqlite::memory:'));
    $hasher = Mockery::mock(Hasher::class);
    $hasher->shouldReceive('check')->never();
    $provider = new DatabaseUserProvider($conn, $hasher, 'foo');
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getAuthPassword')->andReturn(null);
    $result = $provider->validateCredentials($user, ['password' => 'plain']);

    expect($result)->toBeFalse();
});

test('rehash password if required', function () {
    $connection = newConnection();
    $connection->table('foo')->where('id', 3)->update(['password' => (new BcryptHasher(['rounds' => 4]))->make('plain')]);

    $hasher = new BcryptHasher(['rounds' => 5]);
    $provider = new DatabaseUserProvider($connection, $hasher, 'foo');
    $provider->rehashPasswordIfRequired($provider->retrieveById(3), ['password' => 'plain']);

    $hash = $connection->table('foo')->where('id', 3)->value('password');
    expect($hasher->check('plain', $hash))->toBeTrue();
    expect($hasher->needsRehash($hash))->toBeFalse();
});

test('dont rehash password if not required', function () {
    $hasher = new BcryptHasher(['rounds' => 5]);
    $connection = newConnection();
    $connection->table('foo')->where('id', 3)->update(['password' => $hash = $hasher->make('plain')]);

    $provider = new DatabaseUserProvider($connection, $hasher, 'foo');
    $provider->rehashPasswordIfRequired($provider->retrieveById(3), ['password' => 'plain']);

    expect($connection->table('foo')->where('id', 3)->value('password'))->toBe($hash);
});

function newConnection(): SQLiteConnection
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('create table "foo" ("id" integer primary key, "username" text, "group" text, "name" text, "password" text, "remember_token" text)');
    $pdo->exec("insert into \"foo\" values (1, 'dayle', 'three', 'other', null, null)");
    $pdo->exec("insert into \"foo\" values (2, 'sam', 'one', 'third', null, null)");
    $pdo->exec("insert into \"foo\" values (3, 'dayle', 'one', 'taylor', null, 'a')");

    return new SQLiteConnection($pdo);
}

function newDatabaseProvider(): DatabaseUserProvider
{
    return new DatabaseUserProvider(newConnection(), new BcryptHasher, 'foo');
}
