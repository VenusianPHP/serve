<?php

use Mariner\Session\ArraySessionHandler;
use Mariner\Session\AsyncSessionHandler;
use Mariner\Session\CacheBasedSessionHandler;
use Mariner\Session\Store;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\Schema\Blueprint;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-session-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    mkdir($this->dir.'/files');

    // A database file both this process and the pool's workers open.
    touch($this->dir.'/sessions.sqlite');
    $this->app['config']->set('database.connections.sessions', ['driver' => 'sqlite', 'database' => $this->dir.'/sessions.sqlite', 'prefix' => '']);
    $this->app['db']->connection('sessions')->getSchemaBuilder()->create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity');
    });

    $this->app['config']->set('session.files', $this->dir.'/files');
    $this->app['config']->set('session.connection', 'sessions');
    $this->app['config']->set('cache.stores.sessions', ['driver' => 'file', 'path' => $this->dir.'/cache']);
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

/** A fresh handler of the given kind, as one request's session manager builds it. */
function sessionHandler($app, string $kind): SessionHandlerInterface
{
    $app->forgetScopedInstances();

    return $kind === 'cache'
        ? new CacheBasedSessionHandler($app['cache']->store('sessions'), 10)
        : $app['session']->driver($kind)->getHandler();
}

/** The handler's async variant, through the method the framework convention gives it. */
function asyncVariant(SessionHandlerInterface $handler): AsyncSessionHandler
{
    return $handler instanceof CacheBasedSessionHandler ? $handler->async() : $handler->via();
}

dataset('handlers', ['file', 'database', 'cache']);

it('keeps the blocking handlers exactly as they were', function (string $kind) {
    $handler = sessionHandler($this->app, $kind);

    expect($handler->write('a', 'payload-a'))->toBeTrue()
        ->and($handler->read('a'))->toBe('payload-a')
        ->and($handler->destroy('a'))->toBeTrue()
        ->and($handler->read('a'))->toBe('');
})->with('handlers');

it('answers with promises from the async variant, inside a loop task', function (string $kind) {
    $loop = $this->app['event-loop'];
    $async = asyncVariant(sessionHandler($this->app, $kind));

    $written = $async->write('a', 'payload-a');

    expect($written)->toBeInstanceOf(Promise::class)
        ->and($loop->await($loop->async(fn () => [$written->wait(), $async->read('a')->wait()])))->toBe([true, 'payload-a'])
        ->and($loop->await($async->destroy('a')))->toBeTrue()
        ->and($loop->await($async->read('a')))->toBe('');
})->with('handlers');

it('lets two loop tasks overlap their async session I/O, each reading its own', function (string $kind) {
    $loop = $this->app['event-loop'];
    $events = [];
    $request = function (string $id) use ($kind, $loop, &$events) {
        $async = asyncVariant(sessionHandler($this->app, $kind));

        return $loop->async(function () use ($async, $id, &$events) {
            $events[] = "{$id}:start";
            $async->write($id, "payload-{$id}")->wait();
            $read = $async->read($id)->wait();
            $events[] = "{$id}:done";

            return $read;
        });
    };

    $a = $request('a');
    $b = $request('b');

    expect($loop->await($a))->toBe('payload-a')
        ->and($loop->await($b))->toBe('payload-b')
        ->and(array_search('b:start', $events))->toBeLessThan(array_search('a:done', $events));
})->with('handlers');

it('makes a blocking read wait for an async write made before it', function (string $kind) {
    $handler = sessionHandler($this->app, $kind);

    $pending = asyncVariant($handler)->write('a', 'written-async');

    expect($handler->read('a'))->toBe('written-async')
        ->and($pending->settled())->toBeTrue();
})->with('handlers');

it('writes one row when two concurrent requests create the same database session', function () {
    $loop = $this->app['event-loop'];
    $a = asyncVariant(sessionHandler($this->app, 'database'))->write('shared', 'from-a');
    $b = asyncVariant(sessionHandler($this->app, 'database'))->write('shared', 'from-b');

    expect($loop->await($a))->toBeTrue()
        ->and($loop->await($b))->toBeTrue()
        ->and($this->app['db']->connection('sessions')->table('sessions')->count())->toBe(1);
});

it('collects expired file sessions in a worker through the async variant, and in place otherwise', function () {
    $handler = sessionHandler($this->app, 'file');
    foreach (['old-1', 'old-2', 'fresh'] as $id) {
        $handler->write($id, $id);
    }
    touch($this->dir.'/files/old-1', time() - 3600);
    touch($this->dir.'/files/old-2', time() - 3600);

    expect($this->app['event-loop']->await($handler->via()->gc(60)))->toBe(2)
        ->and(file_exists($this->dir.'/files/fresh'))->toBeTrue();

    touch($this->dir.'/files/fresh', time() - 3600);

    expect($handler->gc(60))->toBe(1);
});

it('starts and saves a session through Store::async()', function () {
    $loop = $this->app['event-loop'];
    $handler = sessionHandler($this->app, 'database');

    $id = Voyager\NutsAndBolts\Str::random(40);
    $first = new Store('mariner', $handler, $id);
    $loop->await($first->async()->start());
    $first->put('who', 'alice');
    $loop->await($first->async()->save());

    $second = new Store('mariner', sessionHandler($this->app, 'database'), $id);

    expect($loop->await($second->async()->start()))->toBeTrue()
        ->and($second->get('who'))->toBe('alice');
});

it('answers in-memory handlers with settled promises, and refuses a handler with no async variant', function () {
    $store = new Store('mariner', new ArraySessionHandler(10), 'session-1');

    expect($this->app['event-loop']->await($store->async()->start()))->toBeTrue();

    $custom = new Store('mariner', new class implements SessionHandlerInterface {
        public function open($path, $name): bool { return true; }
        public function close(): bool { return true; }
        public function read($id): string|false { return ''; }
        public function write($id, $data): bool { return true; }
        public function destroy($id): bool { return true; }
        public function gc($max): int|false { return 0; }
    }, 'session-1');

    expect(fn () => $custom->async())->toThrow(LogicException::class, 'has no async variant');
});
