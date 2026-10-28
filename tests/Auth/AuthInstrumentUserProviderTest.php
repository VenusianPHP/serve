<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Authenticatable as AuthenticatableTrait;
use Mariner\Auth\InstrumentUserProvider;
use Mariner\Auth\GenericUser;
use Mariner\Contracts\Auth\Authenticatable;
use Voyager\Contracts\Hashing\Hasher;
use Voyager\Database\Instrument\Model;
use Voyager\Hashing\BcryptHasher;
use Venusian\Serve\Tests\Fixtures\InMemoryDatabase;
use Mockery;

afterEach(fn () => InMemoryDatabase::restore());

test('retrieve by idreturns user', function () {
    $user = newInstrumentProvider()->retrieveById(3);

    expect($user)->toBeInstanceOf(InstrumentProviderUserStub::class);
    expect($user->name)->toBe('taylor');
});

test('retrieve by token returns user', function () {
    $user = newInstrumentProvider()->retrieveByToken(3, 'a');

    expect($user)->toBeInstanceOf(InstrumentProviderUserStub::class);
    expect($user->name)->toBe('taylor');
});

test('retrieve token with bad identifier returns null', function () {
    expect(newInstrumentProvider()->retrieveByToken(99, 'a'))->toBeNull();
});

test('retrieving with only password credential returns null', function () {
    $user = newInstrumentProvider()->retrieveByCredentials(['api_password' => 'foo']);

    expect($user)->toBeNull();
});

test('retrieve by bad token returns null', function () {
    $provider = newInstrumentProvider();

    expect($provider->retrieveByToken(1, 'a'))->toBeNull();
    expect($provider->retrieveByToken(3, 'b'))->toBeNull();
});

test('retrieve by credentials returns user', function () {
    $user = newInstrumentProvider()->retrieveByCredentials(['username' => 'dayle', 'password' => 'foo', 'group' => ['one', 'two']]);

    expect($user)->toBeInstanceOf(InstrumentProviderUserStub::class);
    expect($user->name)->toBe('taylor');
});

test('retrieve by credentials accepts callback', function () {
    $user = newInstrumentProvider()->retrieveByCredentials([function ($builder) {
        $builder->where('username', 'dayle');
        $builder->whereIn('group', ['one', 'two']);
    }]);

    expect($user->name)->toBe('taylor');
});

test('retrieve by credentials with multiply passwords returns null', function () {
    $user = newInstrumentProvider()->retrieveByCredentials([
        'password' => 'dayle',
        'password2' => 'night',
    ]);

    expect($user)->toBeNull();
});

test('credential validation', function () {
    $hasher = new BcryptHasher;
    $provider = new InstrumentUserProvider($hasher, 'foo');
    $user = new GenericUser(['password' => $hasher->make('plain')]);
    $result = $provider->validateCredentials($user, ['password' => 'plain']);

    expect($result)->toBeTrue();
});

test('credential validation failed', function () {
    $hasher = new BcryptHasher;
    $provider = new InstrumentUserProvider($hasher, 'foo');
    $user = new GenericUser(['password' => $hasher->make('plain')]);
    $result = $provider->validateCredentials($user, ['password' => 'wrong']);

    expect($result)->toBeFalse();
});

test('credential validation fails gracefully with null password', function () {
    $hasher = Mockery::mock(Hasher::class);
    $hasher->shouldReceive('check')->never();
    $provider = new InstrumentUserProvider($hasher, 'foo');
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getAuthPassword')->andReturn(null);
    $result = $provider->validateCredentials($user, ['password' => 'plain']);

    expect($result)->toBeFalse();
});

test('rehash password if required', function () {
    $hasher = new BcryptHasher(['rounds' => 5]);
    $provider = newInstrumentProvider($hasher);
    $user = InstrumentProviderUserStub::find(3);
    $user->forceFill(['password' => (new BcryptHasher(['rounds' => 4]))->make('plain')])->save();

    $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);

    $hash = $user->fresh()->password;
    expect($hasher->check('plain', $hash))->toBeTrue();
    expect($hasher->needsRehash($hash))->toBeFalse();
});

test('dont rehash password if not required', function () {
    $hasher = Mockery::mock(Hasher::class);
    $hasher->expects('needsRehash')->with('hash')->andReturn(false);
    $hasher->shouldNotReceive('make');

    $provider = newInstrumentProvider($hasher);
    $user = InstrumentProviderUserStub::find(3);
    $provider->rehashPasswordIfRequired($user, ['password' => 'plain']);

    expect($user->fresh()->password)->toBe('hash');
});

test('models can be created', function () {
    $hasher = new BcryptHasher;
    $provider = new InstrumentUserProvider($hasher, InstrumentProviderUserStub::class);
    $model = $provider->createModel();

    expect($model)->toBeInstanceOf(InstrumentProviderUserStub::class);
});

test('registers query handler', function () {
    $callback = function ($builder) {
        $builder->whereIn('group', ['one', 'two']);
    };

    $provider = newInstrumentProvider();
    $provider->withQuery($callback);
    $user = $provider->retrieveByCredentials([function ($builder) {
        $builder->where('username', 'dayle');
    }]);

    expect($user->name)->toBe('taylor');
    expect($provider->getQueryCallback())->toBe($callback);
});

function newInstrumentProvider(?Hasher $hasher = null)
{
    $pdo = InMemoryDatabase::use()->getPdo();
    $pdo->exec('create table "users" ("id" integer primary key, "username" text, "group" text, "name" text, "remember_token" text, "password" text)');
    $pdo->exec("insert into \"users\" values (1, 'dayle', 'three', 'other', null, null)");
    $pdo->exec("insert into \"users\" values (2, 'sam', 'one', 'third', null, null)");
    $pdo->exec("insert into \"users\" values (3, 'dayle', 'two', 'taylor', 'a', 'hash')");

    return new InstrumentUserProvider($hasher ?? new BcryptHasher, InstrumentProviderUserStub::class);
}

class InstrumentProviderUserStub extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'users';

    public $timestamps = false;
}
