<?php

use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Venusian\Serve\Tests\Fixtures\WebApp;

/*
 * The server against clients that try to hold it up or wear it out, over real sockets: slowloris
 * headers and bodies, endless chunked bodies, header floods, idle and pipelined connections.
 */

beforeEach(function () {
    $this->app = WebApp::boot();
    config(['session.driver' => 'array']);
    $this->loop = $this->app['event-loop'];
    $this->servers = [];
    $this->sockets = [];
});

afterEach(function () {
    foreach ($this->sockets as $socket) {
        is_resource($socket) && fclose($socket);
    }

    foreach ($this->servers as $server) {
        $server->stop();
    }

    WebApp::tearDown($this);
});

function abuseServer($test, array $options = []): HttpServer
{
    $server = (new HttpServer($test->app, new ServerOptions(...[...$options, 'port' => 0])))->attach($test->loop);
    $test->servers[] = $server;

    return $server;
}

function abuseClient($test, HttpServer $server)
{
    $socket = stream_socket_client("tcp://127.0.0.1:{$server->port()}", $code, $message, 2);
    stream_set_blocking($socket, false);

    return $test->sockets[] = $socket;
}

/** Turn the loop for $seconds, calling $each between turns; stop early once $each returns true. */
function abuseRun($loop, float $seconds, ?Closure $each = null): void
{
    $name = 'abuse.pace.'.random_int(0, PHP_INT_MAX);
    $pace = $loop->every(0.005, fn () => null, $name);
    $until = microtime(true) + $seconds;

    try {
        $loop->until(fn () => ($each ? $each() : false) === true || microtime(true) > $until);
    } finally {
        $pace->cancel();
        $loop->forget($name);
    }
}

/** Everything readable on $socket now, and whether the server closed it. */
function abuseRead($socket): array
{
    $bytes = '';

    while (($chunk = (string) fread($socket, 65_536)) !== '') {
        $bytes .= $chunk;
    }

    return [$bytes, feof($socket)];
}

function abuseStatus(string $raw): ?int
{
    return preg_match('#^HTTP/1\.1 (\d{3})#', $raw, $m) ? (int) $m[1] : null;
}

it('answers a body that stops arriving with 408 and closes', function () {
    $server = abuseServer($this, ['body_timeout' => 1.0]);
    $socket = abuseClient($this, $server);
    fwrite($socket, "POST /up HTTP/1.1\r\nHost: h\r\nContent-Length: 1000\r\n\r\n".str_repeat('x', 10));

    $received = '';
    $closed = false;
    abuseRun($this->loop, 5.0, function () use ($socket, &$received, &$closed) {
        [$bytes, $closed] = abuseRead($socket);
        $received .= $bytes;

        return $closed;
    });

    expect(abuseStatus($received))->toBe(408)->and($closed)->toBeTrue();
});

it('answers a chunked body that stops arriving with 408 and closes', function () {
    $server = abuseServer($this, ['body_timeout' => 1.0]);
    $socket = abuseClient($this, $server);
    fwrite($socket, "POST /up HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\n1\r\nx\r\n");

    $received = '';
    $closed = false;
    abuseRun($this->loop, 5.0, function () use ($socket, &$received, &$closed) {
        [$bytes, $closed] = abuseRead($socket);
        $received .= $bytes;

        return $closed;
    });

    expect(abuseStatus($received))->toBe(408)->and($closed)->toBeTrue();
});

it('takes a slow but steady upload that outlasts the header timeout', function () {
    $server = abuseServer($this, ['header_timeout' => 1.0, 'body_timeout' => 1.0]);
    $socket = abuseClient($this, $server);
    fwrite($socket, "POST /up HTTP/1.1\r\nHost: h\r\nContent-Length: 15\r\n\r\n");

    $received = '';
    $sent = 0;
    $last = 0.0;
    abuseRun($this->loop, 6.0, function () use ($socket, &$received, &$sent, &$last) {
        if ($sent < 15 && microtime(true) - $last > 0.2) {
            fwrite($socket, 'x');
            $sent++;
            $last = microtime(true);
        }
        [$bytes] = abuseRead($socket);
        $received .= $bytes;

        return str_contains($received, "\r\n\r\nOK");
    });

    // 15 bytes at one per 0.2s: about 3s of body, past the 1s header timeout, never 1s without a byte.
    expect(abuseStatus($received))->toBe(200);
});

it('refuses more headers than the limit even when they fit the byte limit', function () {
    $server = abuseServer($this);
    $socket = abuseClient($this, $server);
    $headers = implode('', array_map(fn ($i) => "X-{$i}: a\r\n", range(1, 150)));
    fwrite($socket, "GET /up HTTP/1.1\r\nHost: h\r\n{$headers}\r\n");

    $received = '';
    abuseRun($this->loop, 3.0, function () use ($socket, &$received) {
        [$bytes, $closed] = abuseRead($socket);
        $received .= $bytes;

        return $closed;
    });

    expect(strlen($headers))->toBeLessThan(16_384)
        ->and(abuseStatus($received))->toBe(431);
});

it('closes many idle connections once the idle timeout passes', function () {
    $server = abuseServer($this, ['idle_timeout' => 1.0]);
    $sockets = array_map(fn () => abuseClient($this, $server), range(1, 50));

    $closed = [];
    abuseRun($this->loop, 4.0, function () use ($sockets, &$closed) {
        foreach ($sockets as $i => $socket) {
            [, $eof] = abuseRead($socket);
            $eof && $closed[$i] = true;
        }

        return count($closed) === count($sockets);
    });

    expect(count($closed))->toBe(50);
});

it('caps the connections it holds at once, and takes the next when one closes', function () {
    $server = abuseServer($this, ['max_connections' => 3]);
    $held = fn () => count((fn () => $this->connections)->call($server));
    $sockets = array_map(fn () => abuseClient($this, $server), range(1, 5));

    abuseRun($this->loop, 0.5);
    $at_cap = $held();

    fclose($sockets[0]);
    abuseRun($this->loop, 0.5);

    expect($at_cap)->toBe(3)
        ->and($held())->toBe(3)
        ->and($server->maxConnections())->toBe(3);
});

it('keeps its default cap within the process\'s open-file limit, and refuses a setting above it', function () {
    $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
    $probe = <<<PHP
        require '{$autoload}';
        \$default = (new Mariner\\Core\\Server\\ServerOptions)->maxConnections();
        try { new Mariner\\Core\\Server\\ServerOptions(max_connections: 500); \$refused = 'no'; }
        catch (InvalidArgumentException \$e) { \$refused = \$e->getMessage(); }
        echo json_encode([\$default, \$refused]);
        PHP;

    $out = shell_exec('ulimit -n 256 && '.escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($probe));
    [$default, $refused] = json_decode((string) $out, true);

    expect($default)->toBe(256 - Mariner\Core\Server\ServerOptions::FILE_RESERVE)
        ->and($refused)->toContain('max_connections 500 is more than this process can open')->toContain('room for 192 connections');
})->skip(! function_exists('posix_getrlimit') || PHP_OS_FAMILY === 'Windows', 'needs posix_getrlimit and ulimit');

it('stops reading a pipelining client that does not read its answers', function () {
    $max = 65_536;
    $server = abuseServer($this, ['max_buffered' => $max]);

    // One request first: what serving costs the first time (classes, routes) isn't what is buffered.
    $warm = abuseClient($this, $server);
    fwrite($warm, "GET /up HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    abuseRun($this->loop, 1.0, fn () => abuseRead($warm)[1]);

    $socket = abuseClient($this, $server);
    $batch = str_repeat("GET /up HTTP/1.1\r\nHost: h\r\n\r\n", 2_000);
    $flood = function () use ($socket, $batch) {
        @fwrite($socket, $batch);

        return false;
    };

    // The client keeps sending and never reads a response. Once the server stops reading it, what the
    // server holds stops growing, however long the client goes on.
    abuseRun($this->loop, 2.0, $flood);
    gc_collect_cycles();
    $after_two = memory_get_usage();

    abuseRun($this->loop, 2.0, $flood);
    gc_collect_cycles();
    $after_four = memory_get_usage();

    $connections = (fn () => $this->connections)->call($server);
    $connection = end($connections);
    $one_answer = strlen("HTTP/1.1 200 OK\r\n") + 200;

    expect($connection->buffered())->toBeLessThanOrEqual($max + $one_answer)
        ->and($after_four - $after_two)->toBeLessThan($max);
});

it('lets go of a client that stops reading a large response', function () {
    $this->app['router']->get('large', fn () => str_repeat('x', 8 * 1024 * 1024));
    $server = abuseServer($this, ['idle_timeout' => 1.0, 'header_timeout' => 1.0, 'write_timeout' => 1.0]);
    $socket = abuseClient($this, $server);
    fwrite($socket, "GET /large HTTP/1.1\r\nHost: h\r\n\r\n");

    // Never read: the response cannot leave the server's outbox, and the connection must not be held forever.
    abuseRun($this->loop, 4.0);

    expect((fn () => $this->connections)->call($server))->toBe([]);
});
