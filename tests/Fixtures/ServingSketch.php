<?php

namespace Venusian\Serve\Tests\Fixtures;

use ArrayObject;
use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Voyager\Contracts\Sketches\Attributes\Sketch as SketchName;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

/**
 * A sketch that serves the app over HTTP from inside its own run: it ticks, starts a server on its
 * loop mid-run, has a child process request a route that reads state the sketch set, keeps
 * ticking, then drains the server and stops. In 'signal' mode a SIGTERM arrives during a request
 * instead, and the sketch drains on it.
 */
#[SketchName('serving')]
final class ServingSketch extends Sketch
{
    /** 'drain' or 'signal'. */
    public static string $mode = 'drain';

    /** Where the client child writes what it got. */
    public static string $results = '';

    /** @var array<string, mixed> what the run did, for the test to read afterwards */
    public static array $record = [];

    protected ?float $refresh_rate = 100.0;

    private int $ticks = 0;

    private ?HttpServer $server = null;

    private bool $drained = false;

    public function boot(): void
    {
        self::$record = ['ticks_before' => null, 'ticks_after' => null, 'shutdown' => false, 'same_scoped' => null];

        // State a route reads: the sketch's, through the one app both share.
        app()->registerInstance('sketch.state', new ArrayObject(['greeting' => 'hello from the sketch']));

        // A scoped instance resolved here, on the sketch's own (main) scope, before any request.
        app()->scoped('sketch.scoped', fn () => new ArrayObject);
        self::$record['scoped_before'] = spl_object_id(app('sketch.scoped'));

        if (self::$mode === 'signal') {
            app('event-loop')->onStopSignal(function () {
                $this->server?->drain(5.0)->then(fn () => $this->drained = true);

                return true;
            });
        }
    }

    public function loop(array $mail = []): SketchLoopResult
    {
        $this->ticks++;

        if ($this->ticks === 3) {
            $this->server = app(HttpServer::class, ['options' => new ServerOptions(port: 0)])->listen()->attach(app('event-loop'));
            self::$record['ticks_before'] = $this->ticks;
            $this->request(self::$mode === 'signal' ? '/sketch/slow' : '/sketch/state');
        }

        if (self::$mode === 'drain' && ! is_null($this->server) && ! $this->drained && is_file(self::$results) && filesize(self::$results) > 0) {
            self::$record['ticks_after'] = $this->ticks;
            self::$record['same_scoped'] = spl_object_id(app('sketch.scoped')) === self::$record['scoped_before'];
            $this->server->drain(5.0)->then(fn () => $this->drained = true);
        }

        if ($this->drained) {
            self::$record['same_scoped'] ??= spl_object_id(app('sketch.scoped')) === self::$record['scoped_before'];
            self::$record['ticks_after'] ??= $this->ticks;

            return SketchLoopResult::STOP;
        }

        return $this->ticks > 2000 ? SketchLoopResult::STOP : SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        self::$record['shutdown'] = true;
    }

    /** A child process asks for $path and writes what it got to the results file. */
    private function request(string $path): void
    {
        $url = "http://127.0.0.1:{$this->server->port()}{$path}";
        $results = self::$results;

        proc_open([PHP_BINARY, '-r', "file_put_contents('{$results}', (string) @file_get_contents('{$url}'));"], [], $pipes);
    }
}
