<?php

use Mariner\Auth\DatabaseUserProvider;
use Mariner\Auth\InstrumentUserProvider;
use Mariner\Auth\GenericUser;
use Mariner\Auth\Passwords\CacheTokenRepository;
use Mariner\Auth\Passwords\DatabaseTokenRepository;
use Venusian\Serve\Tests\Fixtures\User;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\Schema\Blueprint;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-auth-'.bin2hex(random_bytes(4));
    mkdir($this->dir);

    // A database file both this process and the pool's workers open.
    touch($this->dir.'/auth.sqlite');
    $this->app['config']->set('database.connections.auth', ['driver' => 'sqlite', 'database' => $this->dir.'/auth.sqlite', 'prefix' => '']);
    $schema = $this->app['db']->connection('auth')->getSchemaBuilder();
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->string('password');
        $table->string('remember_token')->nullable();
    });
    $schema->create('password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    $hash = $this->app['hash']->make('secret');
    $this->app['db']->connection('auth')->table('users')->insert([
        ['email' => 'alice@example.test', 'password' => $hash, 'remember_token' => 'alice-token'],
        ['email' => 'bob@example.test', 'password' => $hash, 'remember_token' => 'bob-token'],
    ]);

    $this->app['config']->set('cache.stores.tokens', ['driver' => 'file', 'path' => $this->dir.'/cache']);
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

function databaseProvider($app): DatabaseUserProvider
{
    return new DatabaseUserProvider($app['db']->connection('auth'), $app['hash'], 'users');
}

function instrumentProvider($app): InstrumentUserProvider
{
    return new InstrumentUserProvider($app['hash'], User::class);
}

/** The email a provider's user carries, whichever kind of user it is. */
function emailOf($user): ?string
{
    return $user?->email;
}

dataset('providers', ['database', 'instrument']);

function provider($app, string $kind)
{
    return $kind === 'database' ? databaseProvider($app) : instrumentProvider($app);
}

it('keeps the blocking provider lookups exactly as they were', function (string $kind) {
    $provider = provider($this->app, $kind);

    expect(emailOf($provider->retrieveById(1)))->toBe('alice@example.test')
        ->and(emailOf($provider->retrieveByToken(2, 'bob-token')))->toBe('bob@example.test')
        ->and($provider->retrieveByToken(2, 'wrong'))->toBeNull()
        ->and(emailOf($provider->retrieveByCredentials(['email' => 'bob@example.test', 'password' => 'secret'])))->toBe('bob@example.test')
        ->and($provider->retrieveByCredentials(['password' => 'secret']))->toBeNull();
})->with('providers');

it('answers lookups with promises from the async variant, inside a loop task', function (string $kind) {
    $loop = $this->app['event-loop'];
    $async = provider($this->app, $kind)->via();

    $byId = $async->retrieveById(1);

    expect($byId)->toBeInstanceOf(Promise::class)
        ->and($loop->await($loop->async(fn () => emailOf($byId->wait()))))->toBe('alice@example.test')
        ->and(emailOf($loop->await($async->retrieveByToken(2, 'bob-token'))))->toBe('bob@example.test')
        ->and($loop->await($async->retrieveByToken(2, 'wrong')))->toBeNull()
        ->and(emailOf($loop->await($async->retrieveByCredentials(['email' => 'alice@example.test']))))->toBe('alice@example.test')
        ->and($loop->await($async->retrieveByCredentials(['password' => 'secret'])))->toBeNull();
})->with('providers');

it('returns models from the instrument provider\'s async lookups', function () {
    $user = $this->app['event-loop']->await(instrumentProvider($this->app)->via()->retrieveById(1));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue();
});

it('saves the user through the instrument provider\'s async writes, events in this process and SQL in a worker', function () {
    $provider = instrumentProvider($this->app);
    $user = $provider->retrieveById(1);
    $events = [];
    User::saving(function () use (&$events) {
        $events[] = 'saving';
    });
    User::saved(function () use (&$events) {
        $events[] = 'saved';
    });
    $writes = 0;
    $this->app['db']->connection('auth')->listen(function ($query) use (&$writes) {
        $writes += str_starts_with(strtolower($query->sql), 'update') ? 1 : 0;
    });

    $loop = $this->app['event-loop'];
    $loop->await($provider->via()->updateRememberToken($user, 'fresh-token'));
    $loop->await($provider->via()->rehashPasswordIfRequired($user, ['password' => 'secret'], force: true));

    $fresh = $provider->retrieveById(1);

    expect($writes)->toBe(0)
        ->and($events)->toBe(['saving', 'saved', 'saving', 'saved'])
        ->and($user->timestamps)->toBe((new User)->timestamps)
        ->and($fresh->getRememberToken())->toBe('fresh-token')
        ->and($this->app['hash']->check('secret', $fresh->getAuthPassword()))->toBeTrue()
        ->and(emailOf($provider->retrieveByToken(1, 'fresh-token')))->toBe('alice@example.test');

    User::flushEventListeners();
});

it('skips the rehash when the hash is current', function () {
    $provider = instrumentProvider($this->app);
    $user = $provider->retrieveById(1);
    $user->forceFill(['password' => $this->app['hash']->make('secret')])->save();
    $before = $user->getAuthPassword();

    $this->app['event-loop']->await($provider->via()->rehashPasswordIfRequired($user, ['password' => 'secret']));

    expect($provider->retrieveById(1)->getAuthPassword())->toBe($before);
});

it('lets two loop tasks overlap their async lookups', function (string $kind) {
    $loop = $this->app['event-loop'];
    $events = [];
    $lookup = function (int $id) use ($kind, $loop, &$events) {
        $async = provider($this->app, $kind)->via();

        return $loop->async(function () use ($async, $id, &$events) {
            $events[] = "{$id}:start";
            $email = emailOf($async->retrieveById($id)->wait());
            $events[] = "{$id}:done";

            return $email;
        });
    };

    $a = $lookup(1);
    $b = $lookup(2);

    expect([$loop->await($a), $loop->await($b)])->toBe(['alice@example.test', 'bob@example.test'])
        ->and(array_search('2:start', $events))->toBeLessThan(array_search('1:done', $events));
})->with('providers');

it('writes remember tokens and rehashes passwords through the database provider\'s async variant, ahead of later blocking reads', function () {
    $provider = databaseProvider($this->app);
    $user = new GenericUser(['id' => 1, 'password' => 'not-a-current-hash']);

    $provider->via()->updateRememberToken($user, 'fresh-token');
    $rehash = $provider->via()->rehashPasswordIfRequired($user, ['password' => 'secret']);

    expect(emailOf($provider->retrieveByToken(1, 'fresh-token')))->toBe('alice@example.test')
        ->and($rehash->settled())->toBeTrue()
        ->and($this->app['hash']->check('secret', $provider->retrieveById(1)->getAuthPassword()))->toBeTrue();
});

dataset('token repositories', ['database', 'cache']);

function tokens($app, string $kind)
{
    return $kind === 'database'
        ? new DatabaseTokenRepository($app['db']->connection('auth'), $app['hash'], 'password_reset_tokens', 'hash-key', 3600, 60)
        : new CacheTokenRepository($app['cache']->store('tokens'), $app['hash'], 'hash-key', 3600, 60);
}

function asyncTokens($repository)
{
    return $repository instanceof CacheTokenRepository ? $repository->async() : $repository->via();
}

it('keeps the blocking token repositories exactly as they were', function (string $kind) {
    $repository = tokens($this->app, $kind);
    $user = User::find(1);

    $token = $repository->create($user);

    expect($repository->exists($user, $token))->toBeTrue()
        ->and($repository->exists($user, 'wrong'))->toBeFalse()
        ->and($repository->recentlyCreatedToken($user))->toBeTrue();

    $repository->delete($user);

    expect($repository->exists($user, $token))->toBeFalse();
})->with('token repositories');

it('creates, checks and deletes tokens through the async variant', function (string $kind) {
    $loop = $this->app['event-loop'];
    $async = asyncTokens(tokens($this->app, $kind));
    $user = User::find(1);

    $token = $loop->await($async->create($user));

    expect($token)->toBeString()
        ->and($loop->await($async->exists($user, $token)))->toBeTrue()
        ->and($loop->await($async->exists($user, 'wrong')))->toBeFalse()
        ->and($loop->await($async->recentlyCreatedToken($user)))->toBeTrue();

    $loop->await($async->delete($user));

    expect($loop->await($async->exists($user, $token)))->toBeFalse();
})->with('token repositories');

it('replaces the previous token when creating a new one asynchronously, ahead of later blocking checks', function (string $kind) {
    $repository = tokens($this->app, $kind);
    $user = User::find(1);
    $old = $repository->create($user);

    $pending = asyncTokens($repository)->create($user);

    expect($repository->exists($user, $old))->toBeFalse()
        ->and($repository->exists($user, $this->app['event-loop']->await($pending)))->toBeTrue();
})->with('token repositories');
