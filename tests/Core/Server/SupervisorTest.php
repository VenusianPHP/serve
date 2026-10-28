<?php

use Mariner\Core\Server\Supervisor;
use Mariner\Core\Server\Workers\ProcessWorker;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/serve-supervisor-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    $this->said = [];
    $this->say = function (string $message) { $this->said[] = $message; };
});
afterEach(function () {
    array_map('unlink', glob($this->dir.'/*'));
    rmdir($this->dir);
});

/** A child PHP running $code. */
function php(string $code): ProcessWorker
{
    return new ProcessWorker([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code]);
}

/** Send $signal to this process after $seconds, from a child of its own. */
function signalMeIn(float $seconds, int $signal = SIGTERM): void
{
    $pid = getmypid();
    php('usleep('.(int) ($seconds * 1e6)."); posix_kill({$pid}, {$signal});");
}

/** A child that writes "<index>" to started-<index>, then waits for SIGTERM and writes stopped-<index>. */
function patientChild(string $dir): Closure
{
    return fn (int $index) => php(<<<PHP
        file_put_contents('{$dir}/started-{$index}', (string) getmypid());
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function () { file_put_contents('{$dir}/stopped-{$index}', 'yes'); exit(0); });
        while (true) { usleep(20000); }
        PHP);
}

it('runs every worker, and forwards a stop to each before returning', function () {
    $supervisor = new Supervisor(patientChild($this->dir), 3, $this->say);
    signalMeIn(1.0);

    expect($supervisor->run())->toBe(0)
        ->and(glob($this->dir.'/started-*'))->toHaveCount(3)
        ->and(glob($this->dir.'/stopped-*'))->toHaveCount(3)
        ->and($this->said)->toBe([]);
})->skip(! function_exists('pcntl_signal') || ! function_exists('posix_kill'), 'needs pcntl and posix');

it('restarts a worker that dies', function () {
    $dir = $this->dir;
    $command = fn (int $index) => php(<<<PHP
        if (! file_exists('{$dir}/first')) { file_put_contents('{$dir}/first', 'x'); exit(7); }
        file_put_contents('{$dir}/second', 'x');
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => exit(0));
        while (true) { usleep(20000); }
        PHP);

    $supervisor = new Supervisor($command, 1, $this->say);
    signalMeIn(2.0);

    expect($supervisor->run())->toBe(0)
        ->and(file_exists($this->dir.'/second'))->toBeTrue()
        ->and($this->said)->toBe(['Worker 0 exited with status 7; restarting in 0.5s.']);
})->skip(! function_exists('pcntl_signal') || ! function_exists('posix_kill'), 'needs pcntl and posix');

it('gives up on a worker that keeps dying, stopping the others and saying why', function () {
    $dir = $this->dir;
    $command = fn (int $index) => $index === 0
        ? php('exit(3);')
        : patientChild($dir)($index);

    $supervisor = new Supervisor($command, 2, $this->say, max_restarts: 2, window: 60.0);

    expect($supervisor->run())->toBe(1)
        ->and(end($this->said))->toBe('Worker 0 died 3 times within 60s (last with status 3): giving up and stopping the others.')
        ->and(file_exists($this->dir.'/stopped-1'))->toBeTrue();
})->skip(! function_exists('pcntl_signal'), 'needs pcntl');
