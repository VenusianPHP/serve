<?php

namespace Mariner\Core\Server\Workers;

use Mariner\Core\Server\Http1\Limits;
use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServedRequest;
use Mariner\Core\Server\ServerOptions;
use parallel\Channel;
use parallel\Events;
use RuntimeException;
use Voyager\Contracts\Console\Kernel;

/**
 * What a thread worker runs: boot the app, serve on its loop with the port shared, and drain then
 * stop when "stop" arrives on the control channel.
 *
 * @internal Run by ThreadWorker inside its thread.
 */
final class ThreadServe
{
    /**
     * @param  array<string, mixed>  $options
     * @return int  the loop's exit status
     */
    public static function run(string $bootstrap, int $index, array $options, float $drain_timeout, Channel $control): int
    {
        // An exception, not require's fatal error: a fatal inside a thread would end the whole process.
        if (! is_file($bootstrap)) {
            throw new RuntimeException("The app's bootstrap file is missing: {$bootstrap}");
        }

        $app = require $bootstrap;
        $app->make(Kernel::class)->bootstrap();

        $loop = $app->make('event-loop');

        // The process's signals belong to the supervisor: a thread's loop must not watch them.
        $loop->ignoreStopSignals();

        $lines = $options['lines'] ?? false;
        unset($options['lines']);

        $server = (new HttpServer($app, new ServerOptions(...[...$options, 'limits' => new Limits(...$options['limits'])])))->attach($loop);

        if ($lines) {
            $server->onRequest(function (ServedRequest $served) use ($index) {
                fwrite(STDOUT, sprintf("[%d]   %s %3d %s %s %.2f ms\n", $index, date('H:i:s'), $served->status, $served->method, $served->path, $served->seconds * 1000));
            });
        }

        $events = new Events;
        $events->setBlocking(false);
        $events->addChannel($control);

        $loop->every(0.1, function () use ($events, $control, $server, $loop, $drain_timeout) {
            if (is_null($events->poll())) {
                return;
            }

            $events->addChannel($control);   // poll() takes the channel out of the set
            $server->drain($drain_timeout)->then(fn () => $loop->stop(0));
        }, 'serve.worker.control');

        return $loop->run();
    }
}
