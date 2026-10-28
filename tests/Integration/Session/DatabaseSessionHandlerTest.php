<?php

namespace Venusian\Serve\Tests\Integration\Session;

use Mariner\Session\DatabaseSessionHandler;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('session'));
afterAll(fn () => IntegrationTestCase::withMigrations());

test('basic read write functionality', function () {
    $connection = $this->app['db']->connection();
    $handler = new DatabaseSessionHandler($connection, 'sessions', 1);
    $handler->setContainer($this->app);

    // read non-existing session id:
    expect($handler->read('invalid_session_id'))->toBe('');

    // open and close:
    expect($handler->open('', ''))->toBeTrue();
    expect($handler->close())->toBeTrue();

    // write and read:
    expect($handler->write('valid_session_id_2425', json_encode(['foo' => 'bar'])))->toBeTrue();
    expect(json_decode($handler->read('valid_session_id_2425'), true))->toEqual(['foo' => 'bar']);
    expect($connection->table('sessions')->count())->toEqual(1);

    $session = $connection->table('sessions')->first();
    expect($session->user_agent)->not->toBeNull();
    expect($session->ip_address)->not->toBeNull();

    // re-write and read:
    expect($handler->write('valid_session_id_2425', json_encode(['over' => 'ride'])))->toBeTrue();
    expect(json_decode($handler->read('valid_session_id_2425'), true))->toEqual(['over' => 'ride']);
    expect($connection->table('sessions')->count())->toEqual(1);

    // handler object writes only one session id:
    expect($handler->write('other_id', 'data'))->toBeTrue();
    expect($connection->table('sessions')->count())->toEqual(1);

    $handler->setExists(false);
    expect($handler->write('other_id', 'data'))->toBeTrue();
    expect($connection->table('sessions')->count())->toEqual(2);

    // read expired:
    Carbon::setTestNow(Carbon::now()->addMinutes(2));
    expect($handler->read('valid_session_id_2425'))->toBe('');

    // rewriting an expired session-id, makes it live:
    expect($handler->write('valid_session_id_2425', json_encode(['come' => 'alive'])))->toBeTrue();
    expect(json_decode($handler->read('valid_session_id_2425'), true))->toEqual(['come' => 'alive']);
});

test('garbage collector', function () {
    $connection = $this->app['db']->connection();

    $handler = new DatabaseSessionHandler($connection, 'sessions', 1, $this->app);
    Carbon::setTestNow($now = Carbon::now());
    $handler->write('simple_id_1', 'abcd');
    expect($handler->gc(1))->toEqual(0);

    Carbon::setTestNow($now->addSeconds(2));

    $handler = new DatabaseSessionHandler($connection, 'sessions', 1, $this->app);
    $handler->write('simple_id_2', 'abcd');
    expect($handler->gc(2))->toEqual(1);
    expect($connection->table('sessions')->count())->toEqual(1);

    Carbon::setTestNow($now->addSeconds(2));

    expect($handler->gc(1))->toEqual(1);
    expect($connection->table('sessions')->count())->toEqual(0);
});

test('destroy', function () {
    $connection = $this->app['db']->connection();
    $handler1 = new DatabaseSessionHandler($connection, 'sessions', 1, $this->app);
    $handler2 = clone $handler1;

    $handler1->write('id_1', 'some data');
    $handler2->write('id_2', 'some data');

    // destroy invalid session-id:
    expect($handler1->destroy('invalid_session_id'))->toEqual(true);
    // nothing deleted:
    expect($connection->table('sessions')->count())->toEqual(2);

    // destroy valid session-id:
    expect($handler2->destroy('id_1'))->toEqual(true);
    // only one row is deleted:
    expect($connection->table('sessions')->where('id', 'id_2')->count())->toEqual(1);
});

test('it can work without container', function () {
    $connection = $this->app['db']->connection();
    $handler = new DatabaseSessionHandler($connection, 'sessions', 1);

    // write and read:
    expect($handler->write('session_id', 'some data'))->toBeTrue();
    expect($handler->read('session_id'))->toBe('some data');
    expect($connection->table('sessions')->count())->toEqual(1);

    $session = $connection->table('sessions')->first();
    expect($session->user_agent)->toBeNull();
    expect($session->ip_address)->toBeNull();
    expect($session->user_id)->toBeNull();
});
