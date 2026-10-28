<?php

use Mariner\Core\Server\Supervisor;
use Mariner\Core\Server\Workers\ThreadWorker;
use Voyager\IOPools\WorkerPools\Thread\ThreadSupport;

/*
 * Thread workers under the Supervisor: each a parallel\Runtime booting the app and serving on its own
 * loop, stopped by a message on its channel. The process's signals stay the supervisor's.
 */

beforeEach(function () {
    $this->autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
    $this->bootstrap = dirname(__DIR__, 2).'/Fixtures/app/bootstrap/app.php';
    $this->said = [];
});

/** Plain options for a thread worker on $port, as ServeCommand hands them over. */
function threadOptions(int $port): array
{
    return [
        'host' => '127.0.0.1', 'port' => $port, 'max_in_flight' => 1,
        'limits' => ['request_line' => 8_192, 'header_bytes' => 16_384, 'headers' => 100, 'body' => 1_048_576],
        'header_timeout' => 5.0, 'idle_timeout' => 5.0, 'https' => false, 'health' => '/up',
        'reuse_port' => false, 'lines' => false,
    ];
}

function freeThreadPort(): int
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    return $port;
}

it('serves from two thread workers, and on SIGTERM the supervisor stops them through their channels', function () {
    $ports = [freeThreadPort(), freeThreadPort()];
    $results = sys_get_temp_dir().'/serve-threads-'.bin2hex(random_bytes(4));
    $parent = getmypid();

    // A child asks each worker for / once both answer, then sends the process SIGTERM.
    proc_open([PHP_BINARY, '-r', <<<PHP
        \$answers = [];
        foreach ([{$ports[0]}, {$ports[1]}] as \$port) {
            for (\$try = 0; \$try < 100; \$try++) {
                \$body = @file_get_contents("http://127.0.0.1:{\$port}/");
                if (\$body !== false) { \$answers[] = \$body; break; }
                usleep(100000);
            }
        }
        file_put_contents('{$results}', json_encode(\$answers));
        posix_kill({$parent}, SIGTERM);
        PHP], [], $pipes);

    $supervisor = new Supervisor(
        fn (int $index) => new ThreadWorker($this->autoload, $this->bootstrap, $index, threadOptions($ports[$index]), 5.0),
        2,
        function (string $message) { $this->said[] = $message; },
        stop_timeout: 15.0,
    );

    $status = $supervisor->run();

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($results), true))->toBe(['home', 'home'])
        ->and($this->said)->toBe([]);

    unlink($results);
})->skip(! ThreadSupport::probe()->supported || ! function_exists('posix_kill'), 'needs threads and posix: '.ThreadSupport::probe()->reason);

it('restarts a thread worker whose app fails to boot, then gives up, naming the failure', function () {
    $supervisor = new Supervisor(
        fn (int $index) => new ThreadWorker($this->autoload, '/nowhere/bootstrap/app.php', $index, threadOptions(freeThreadPort()), 1.0),
        1,
        function (string $message) { $this->said[] = $message; },
        max_restarts: 2,
        stop_timeout: 5.0,
    );

    expect($supervisor->run())->toBe(1)
        ->and(count($this->said))->toBe(3)
        ->and(end($this->said))->toContain('Worker 0 died 3 times')->toContain('/nowhere/bootstrap/app.php');
})->skip(! ThreadSupport::probe()->supported, 'needs threads: '.ThreadSupport::probe()->reason);
