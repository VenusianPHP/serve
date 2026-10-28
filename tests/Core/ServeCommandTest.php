<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;

beforeEach(function () {
    $this->app = WebApp::boot();
    config(['session.driver' => 'array']);
});
afterEach(fn () => WebApp::tearDown($this));

/** A port nothing listens on right now. */
function freePort(): int
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    return $port;
}

/** Run `serve` with $options; $whileServing runs as a loop task once it is up. */
function runServe($app, array $options, ?Closure $whileServing = null): array
{
    $loop = $app['event-loop'];

    if ($whileServing) {
        $loop->at(0.2, fn () => $loop->async($whileServing));
    }

    $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
    $status = $app->make(ConsoleKernel::class)->handle(new ArrayInput(['command' => 'serve', ...$options]), $output);

    return [$status, $output->fetch()];
}

it('serves until SIGTERM, then stops cleanly, printing a line for each request', function () {
    $port = freePort();
    $answer = '';

    [$status, $output] = runServe($this->app, ['--port' => $port], function () use ($port, &$answer) {
        $loop = $this->app['event-loop'];
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}");
        fwrite($socket, "GET / HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
        stream_set_blocking($socket, false);
        $pace = $loop->every(0.001, fn () => null, 'test.serve.pace');

        $loop->until(function () use ($socket, &$answer) {
            $answer .= (string) fread($socket, 65_536);

            return feof($socket);
        });

        $pace->cancel();
        $loop->forget('test.serve.pace');
        posix_kill(getmypid(), SIGTERM);
    });

    expect($status)->toBe(0)
        ->and($answer)->toStartWith('HTTP/1.1 200 OK')->toEndWith('home')
        ->and($output)->toContain("Serving on http://127.0.0.1:{$port}, up to ".(new Mariner\Core\Server\ServerOptions)->maxConnections().' connections at once')
        ->and($output)->toMatch('/200 GET \/ [\d.]+ ms/');
})->skip(! function_exists('pcntl_signal') || ! function_exists('posix_kill'), 'needs pcntl and posix to signal itself');

it('answers the request in flight on SIGTERM before it stops', function () {
    $port = freePort();
    $answer = '';
    $this->app['router']->get('/slow', function () {
        posix_kill(getmypid(), SIGTERM);    // the stop arrives while this request is being answered
        \Venusian\Serve\Tests\Fixtures\LoopPause::yield();
        \Venusian\Serve\Tests\Fixtures\LoopPause::yield();

        return 'finished';
    });

    $socket = null;

    [$status, $output] = runServe($this->app, ['--port' => $port], function () use ($port, &$socket) {
        $socket = stream_socket_client("tcp://127.0.0.1:{$port}");
        fwrite($socket, "GET /slow HTTP/1.1\r\nHost: h\r\n\r\n");
    });

    // The server answered and closed before its loop stopped: read what it sent, to the end.
    stream_set_timeout($socket, 5);
    $answer = stream_get_contents($socket);

    expect($status)->toBe(0)
        ->and($answer)->toEndWith('finished')
        ->and($answer)->toContain('Connection: close')
        ->and($output)->toContain('Stopping: answering the requests in flight.');
})->skip(! function_exists('pcntl_signal') || ! function_exists('posix_kill'), 'needs pcntl and posix to signal itself');

it('refuses more than one request in flight off Linux, saying why', function () {
    [$status, $output] = runServe($this->app, ['--port' => freePort(), '--max-in-flight' => 2]);

    expect($status)->toBe(1)
        ->and($output)->toContain('max_in_flight above 1 needs Linux');
})->skip(PHP_OS_FAMILY === 'Linux', 'Linux runs more than one request in flight');

it('says what is wrong when it cannot listen', function () {
    $taken = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($taken, false), ':'), 1);

    [$status, $output] = runServe($this->app, ['--port' => $port]);
    fclose($taken);

    expect($status)->toBe(1)
        ->and($output)->toContain("Could not listen on tcp://127.0.0.1:{$port}");
});

it('refuses more than one worker off Linux, saying why', function () {
    [$status, $output] = runServe($this->app, ['--port' => freePort(), '--workers' => 2]);

    expect($status)->toBe(1)
        ->and($output)->toContain('More than one worker needs Linux');
})->skip(PHP_OS_FAMILY === 'Linux', 'Linux runs more than one worker');

it('runs workers sharing one port, each answering, until SIGTERM', function () {
    $root = WebApp::copy();
    $vendor = dirname(__DIR__, 2).'/vendor/autoload.php';
    file_put_contents($root.'/computer', <<<PHP
        <?php
        require '{$vendor}';
        \$app = require '{$root}/bootstrap/app.php';
        exit(\$app->handleInquiry(new Symfony\\Component\\Console\\Input\\ArgvInput));
        PHP);

    $port = freePort();
    $results = $root.'/results';
    $parent = getmypid();
    proc_open([PHP_BINARY, '-r', <<<PHP
        \$answers = [];
        for (\$try = 0; \$try < 100 && count(\$answers) < 4; \$try++) {
            \$body = @file_get_contents('http://127.0.0.1:{$port}/');
            \$body === false ? usleep(100000) : \$answers[] = \$body;
        }
        file_put_contents('{$results}', json_encode(\$answers));
        posix_kill({$parent}, SIGTERM);
        PHP], [], $pipes);

    $argv = $_SERVER['argv'];
    $_SERVER['argv'][0] = $root.'/computer';

    try {
        [$status, $output] = runServe($this->app, ['--port' => $port, '--workers' => 2]);
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect($status)->toBe(0)
        ->and($output)->toContain(Voyager\IOPools\WorkerPools\Thread\ThreadSupport::probe()->supported ? 'with 2 thread workers' : 'with 2 process workers')
        ->and($output)->toContain(Voyager\IOPools\WorkerPools\Thread\ThreadSupport::probe()->supported ? 'Driver auto: threads' : 'Driver auto: processes')
        ->and(json_decode((string) file_get_contents($results), true))->toBe(['home', 'home', 'home', 'home']);

    (new Voyager\Filesystem\Filesystem)->deleteDirectory($root);
})->skip(PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill'), 'workers share a port only on Linux');
