<?php

use Mariner\Contracts\Server\HttpServer as HttpServerContract;
use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Venusian\Serve\Tests\Fixtures\WebApp;

/*
 * The HttpServer contract: what a package that cannot name Mariner\Core resolves, attaches to its
 * loop and drains, with ServerOptions replaced by plain config keys.
 */

beforeEach(function () {
    $this->app = WebApp::boot();
    config(['session.driver' => 'array']);
    $this->loop = $this->app['event-loop'];
    $this->servers = [];
});

afterEach(function () {
    foreach ($this->servers as $server) {
        $server->stop();
    }

    WebApp::tearDown($this);
});

it('resolves the contract to an HttpServer that listens, attaches and answers', function () {
    $server = $this->app->make(HttpServerContract::class, ['options' => ['port' => 0]]);
    $this->servers[] = $server;

    expect($server)->toBeInstanceOf(HttpServerContract::class)
        ->toBeInstanceOf(HttpServer::class)
        ->and($server->port())->toBeNull();

    expect($server->attach($this->loop))->toBe($server)
        ->and($server->port())->toBeInt()->toBeGreaterThan(0);

    $socket = stream_socket_client("tcp://127.0.0.1:{$server->port()}", $code, $message, 2);
    fwrite($socket, "GET /up HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    stream_set_blocking($socket, false);

    $raw = '';
    $this->loop->until(function () use ($socket, &$raw) {
        $raw .= (string) fread($socket, 65_536);

        return str_contains($raw, "\r\n\r\nOK");
    });
    fclose($socket);

    expect($raw)->toStartWith('HTTP/1.1 200');
});

it('lays array options over config/serve.php and honours the host', function () {
    config(['serve.max_in_flight' => 3, 'serve.host' => '127.0.0.1']);

    $server = $this->app->make(HttpServerContract::class, ['options' => ['port' => 0, 'host' => '0.0.0.0']]);
    $this->servers[] = $server;

    expect($server->options->host)->toBe('0.0.0.0')
        ->and($server->options->port)->toBe(0)
        ->and($server->options->max_in_flight)->toBe(3);

    $server->listen();

    expect(stream_socket_get_name((new ReflectionProperty($server, 'listener'))->getValue($server), false))
        ->toStartWith('0.0.0.0:');
});

it('reads config/serve.php when no options are given', function () {
    config(['serve.port' => 0, 'serve.host' => '127.0.0.1', 'serve.max_in_flight' => 2]);

    $server = $this->app->make(HttpServerContract::class);

    expect($server->options->max_in_flight)->toBe(2)
        ->and($server->options->host)->toBe('127.0.0.1');
});

it('still takes a ServerOptions, through the class and through the contract', function () {
    $options = new ServerOptions(port: 0, max_in_flight: 4);

    $by_class = $this->app->make(HttpServer::class, ['options' => $options]);
    $by_contract = $this->app->make(HttpServerContract::class, ['options' => $options]);

    expect($by_class->options)->toBe($options)
        ->and($by_contract->options)->toBe($options);
});

it('makes a new server each time, and drains through the contract', function () {
    $first = $this->app->make(HttpServerContract::class, ['options' => ['port' => 0]]);
    $second = $this->app->make(HttpServerContract::class, ['options' => ['port' => 0]]);
    $this->servers[] = $first;
    $this->servers[] = $second;

    expect($first)->not->toBe($second);

    $first->attach($this->loop);
    $drained = $first->drain(1.0);

    $this->loop->until(fn () => $drained->settled());

    expect($drained->settled())->toBeTrue();
});
