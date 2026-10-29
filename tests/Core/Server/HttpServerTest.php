<?php

use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServedRequest;
use Mariner\Core\Server\ServerOptions;
use Mariner\Http\Events\RequestHandled;
use Mariner\Http\Request;
use Mariner\Core\Server\Http1\Limits;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Venusian\Serve\Tests\Fixtures\LoopPause;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Debug\ExceptionHandler;

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

/** A server on a free port, attached to the test's loop. */
function serving($test, array $options = []): HttpServer
{
    $server = (new HttpServer($test->app, new ServerOptions(...[...$options, "port" => 0])))->attach($test->loop);
    $test->servers[] = $server;

    return $server;
}

/** A client socket to $server, non-blocking, so the loop keeps turning while it waits. */
function clientOf(HttpServer $server)
{
    $socket = stream_socket_client("tcp://127.0.0.1:{$server->port()}", $code, $message, 2);
    stream_set_blocking($socket, false);

    return $socket;
}

/**
 * Send $raw and read until $complete says what arrived is all of it, or $timeout passes.
 *
 * @param  resource  $socket
 */
function exchange($loop, $socket, string $raw, ?Closure $complete = null, float $timeout = 5.0): string
{
    if ($raw !== '') {
        stream_set_blocking($socket, true);
        fwrite($socket, $raw);
        stream_set_blocking($socket, false);
    }

    $received = '';
    $deadline = microtime(true) + $timeout;
    $complete ??= fn (string $received, $socket) => feof($socket);

    // A client elsewhere reads as bytes arrive; this one reads when the loop turns, so it keeps
    // the loop turning while it waits.
    $name = 'test.client.'.spl_object_id((object) []).random_int(0, PHP_INT_MAX);
    $pace = $loop->every(0.001, fn () => null, $name);

    try {
        $loop->until(function () use ($socket, &$received, $complete, $deadline) {
            while (($bytes = (string) fread($socket, 65_536)) !== '') {
                $received .= $bytes;
            }

            return $complete($received, $socket) || microtime(true) > $deadline;
        });
    } finally {
        $pace->cancel();
        $loop->forget($name);
    }

    return $received;
}

/** Whether $received holds $count whole responses with Content-Length bodies. */
function answers(int $count): Closure
{
    return function (string $received) use ($count) {
        $seen = 0;

        while (($end = strpos($received, "\r\n\r\n")) !== false) {
            preg_match('/\r\nContent-Length: (\d+)/i', substr($received, 0, $end), $length);
            $total = $end + 4 + (int) ($length[1] ?? 0);

            if (strlen($received) < $total) {
                return false;
            }

            $received = substr($received, $total);
            $seen++;
        }

        return $seen >= $count;
    };
}

/** [status, headers (lower-cased), body] of the one response in $raw. */
function parsedAnswer(string $raw): array
{
    [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    $lines = explode("\r\n", $head);
    $status = (int) explode(' ', array_shift($lines))[1];
    $headers = [];

    foreach ($lines as $line) {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)][] = trim($value);
    }

    return [$status, $headers, $body];
}

/** A chunked body, decoded. */
function unchunk(string $body): string
{
    $out = '';

    while (preg_match('/^([0-9a-f]+)\r\n/i', $body, $size)) {
        $length = hexdec($size[1]);
        $out .= substr($body, strlen($size[0]), $length);
        $body = substr($body, strlen($size[0]) + $length + 2);
    }

    return $out;
}

it('answers a request through the app\'s kernel, closing when asked', function () {
    $server = serving($this);

    [$status, $headers, $body] = parsedAnswer(exchange($this->loop, clientOf($server), "GET / HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n"));

    expect($status)->toBe(200)
        ->and($body)->toBe('home')
        ->and($headers['content-length'])->toBe(['4'])
        ->and($headers['connection'])->toBe(['close']);
});

it('keeps a connection open for the next request, pipelined ones answered in order', function () {
    $server = serving($this);
    $this->app['router']->get('/echo/{word}', fn (string $word) => $word);
    $socket = clientOf($server);

    $first = exchange($this->loop, $socket, "GET /echo/one HTTP/1.1\r\nHost: h\r\n\r\n", answers(1));
    $pipelined = exchange($this->loop, $socket, "GET /echo/two HTTP/1.1\r\nHost: h\r\n\r\nGET /echo/three HTTP/1.1\r\nHost: h\r\n\r\n", answers(2));

    expect(parsedAnswer($first)[2])->toBe('one')
        ->and(parsedAnswer($first)[1]['connection'])->toBe(['keep-alive'])
        ->and(preg_match_all('/\r\n\r\n(two|three)/', $pipelined, $bodies))->toBe(2)
        ->and($bodies[1])->toBe(['two', 'three']);
});

it('answers HEAD with the headers only, 404 through the renderer, and the health check without the app', function () {
    $server = serving($this);
    $socket = clientOf($server);

    $head = exchange($this->loop, $socket, "HEAD / HTTP/1.1\r\nHost: h\r\n\r\n", fn ($r) => str_contains($r, "\r\n\r\n"));
    $missing = exchange($this->loop, $socket, "GET /nowhere HTTP/1.1\r\nHost: h\r\nAccept: application/json\r\n\r\n", answers(1));
    $health = exchange($this->loop, $socket, "GET /up HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");

    expect(parsedAnswer($head)[0])->toBe(200)
        ->and(parsedAnswer($head)[1])->not->toHaveKey('content-length')
        ->and(parsedAnswer($head)[2])->toBe('')
        ->and(parsedAnswer($missing)[0])->toBe(404)
        ->and(parsedAnswer($health))->toMatchArray([0 => 200, 2 => 'OK']);
});

it('reads forms and uploads, and deletes the upload once answered', function () {
    $server = serving($this);
    $kept = [];
    $this->app['router']->post('/upload', function (Request $request) use (&$kept) {
        $kept[] = $request->file('doc')->getPathname();

        return $request->input('title').':'.$request->file('doc')->get();
    });

    $body = "--B\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nNotes\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"doc\"; filename=\"n.txt\"\r\n\r\nremember\r\n--B--";
    $raw = "POST /upload HTTP/1.1\r\nHost: h\r\nConnection: close\r\nContent-Type: multipart/form-data; boundary=B\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body;

    [$status, , $text] = parsedAnswer(exchange($this->loop, clientOf($server), $raw));

    expect([$status, $text])->toBe([200, 'Notes:remember'])
        ->and($kept)->toHaveCount(1)
        ->and(file_exists($kept[0]))->toBeFalse();
});

it('sends a file in parts as the socket takes them, and a range of it', function () {
    $server = serving($this);
    $path = tempnam(sys_get_temp_dir(), 'serve-download-');
    file_put_contents($path, $content = random_bytes(300_000));
    $this->app['router']->get('/download', fn () => new BinaryFileResponse($path));

    $whole = exchange($this->loop, clientOf($server), "GET /download HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    $part = exchange($this->loop, clientOf($server), "GET /download HTTP/1.1\r\nHost: h\r\nRange: bytes=10-19\r\nConnection: close\r\n\r\n");

    unlink($path);

    expect(parsedAnswer($whole)[0])->toBe(200)
        ->and(parsedAnswer($whole)[2] === $content)->toBeTrue()
        ->and(parsedAnswer($part)[0])->toBe(206)
        ->and(parsedAnswer($part)[2])->toBe(substr($content, 10, 10));
});

it('streams a response as it is made, chunked, across the waits it makes', function () {
    $server = serving($this);
    $this->app['router']->get('/stream', fn () => new StreamedResponse(function () {
        foreach (['a', 'b', 'c'] as $part) {
            echo $part;
            LoopPause::yield();
        }
    }));

    [$status, $headers, $body] = parsedAnswer(exchange($this->loop, clientOf($server), "GET /stream HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n"));

    expect($status)->toBe(200)
        ->and($headers['transfer-encoding'])->toBe(['chunked'])
        ->and($headers)->not->toHaveKey('content-length')
        ->and($body)->toBe("1\r\na\r\n1\r\nb\r\n1\r\nc\r\n0\r\n\r\n")
        ->and(unchunk($body))->toBe('abc');
});

it('refuses what it can\'t accept with the status for it, then closes', function (string $raw, int $status, array $limits) {
    $server = serving($this, $limits === [] ? [] : ['limits' => new Limits(...$limits)]);

    $answer = exchange($this->loop, clientOf($server), $raw);

    expect(parsedAnswer($answer)[0])->toBe($status)
        ->and(parsedAnswer($answer)[1]['connection'])->toBe(['close']);
})->with([
    'a malformed request' => ["GET / HTTP/1.1\r\nHost: h\r\nX-Bad : v\r\n\r\n", 400, []],
    'smuggling' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 1\r\nTransfer-Encoding: chunked\r\n\r\n", 400, []],
    'too large a body' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 2000\r\n\r\n", 413, ['body' => 1000]],
]);

it('answers one request at a time with max_in_flight 1, in arrival order', function () {
    $server = serving($this, ['max_in_flight' => 1]);
    $order = [];
    $this->app['router']->get('/slow/{n}', function (string $n) use (&$order) {
        $order[] = "start {$n}";
        LoopPause::yield();
        LoopPause::yield();
        $order[] = "end {$n}";

        return $n;
    });

    $one = clientOf($server);
    $two = clientOf($server);
    fwrite($one, "GET /slow/1 HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    fwrite($two, "GET /slow/2 HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    exchange($this->loop, $one, '');
    exchange($this->loop, $two, '');

    expect($order)->toBe(['start 1', 'end 1', 'start 2', 'end 2']);
});

it('answers several requests at once by default, each in its own loop task', function () {
    $server = serving($this);
    $order = [];
    $this->app['router']->get('/slow/{n}', function (string $n) use (&$order) {
        $order[] = "start {$n}";
        LoopPause::yield();
        LoopPause::yield();
        $order[] = "end {$n}";

        return $n;
    });

    $one = clientOf($server);
    $two = clientOf($server);
    fwrite($one, "GET /slow/1 HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    fwrite($two, "GET /slow/2 HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    exchange($this->loop, $one, '');
    exchange($this->loop, $two, '');

    expect($server->options->max_in_flight)->toBe(10)
        ->and($order)->toBe(['start 1', 'start 2', 'end 1', 'end 2']);
});

it('lets a request wait for another request to answer it', function () {
    $server = serving($this);
    $released = false;
    $this->app['router']->get('/wait', function () use (&$released) {
        while (! $released) {
            LoopPause::yield();
        }

        return 'released';
    });
    $this->app['router']->get('/release', function () use (&$released) {
        $released = true;

        return 'ok';
    });

    $waiting = clientOf($server);
    fwrite($waiting, "GET /wait HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    $release = exchange($this->loop, clientOf($server), "GET /release HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    $wait = exchange($this->loop, $waiting, '');

    expect(parsedAnswer($release)[2])->toBe('ok')
        ->and(parsedAnswer($wait)[2])->toBe('released');
});

/** Turn $loop until $done says so, or $timeout passes. */
function turnUntil($loop, Closure $done, float $timeout = 5.0): void
{
    $deadline = microtime(true) + $timeout;
    $name = 'test.turn.'.random_int(0, PHP_INT_MAX);
    $pace = $loop->every(0.001, fn () => null, $name);

    try {
        $loop->until(fn () => $done() || microtime(true) > $deadline);
    } finally {
        $pace->cancel();
        $loop->forget($name);
    }
}

it('cancels a stream whose client hangs up, running its finally and freeing its place', function () {
    $server = serving($this, ['max_in_flight' => 1]);
    $events = 0;
    $unwound = false;
    $this->app['router']->get('/forever', function () use (&$events, &$unwound) {
        return new StreamedResponse(function () use (&$events, &$unwound) {
            try {
                while (true) {
                    echo 'tick';
                    $events++;
                    LoopPause::yield();
                }
            } finally {
                $unwound = true;
            }
        });
    });

    $gone = clientOf($server);
    fwrite($gone, "GET /forever HTTP/1.1\r\nHost: h\r\n\r\n");
    turnUntil($this->loop, function () use (&$events) { return $events >= 3; });
    fclose($gone);
    turnUntil($this->loop, function () use (&$unwound) { return $unwound; });

    // max_in_flight is 1: the stream's place is free again only if it was let go
    $answer = exchange($this->loop, clientOf($server), "GET /up HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");

    expect($unwound)->toBeTrue()
        ->and(parsedAnswer($answer)[0])->toBe(200);
});

it('cancels a request waiting on the loop when its client hangs up, as 499, reporting nothing', function () {
    $server = serving($this);
    $reported = [];
    $this->app->registerInstance(ExceptionHandler::class, new class($reported) implements ExceptionHandler {
        public function __construct(private array &$reported) {}

        public function report(Throwable $e): void
        {
            $this->reported[] = $e->getMessage();
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function renderForConsole(OutputInterface $output, Throwable $e): void {}
    });
    $seen = [];
    $server->onRequest(function (ServedRequest $served) use (&$seen) {
        $seen[] = [$served->path, $served->status];
    });
    $waiting = false;
    $this->app['router']->get('/wait', function () use (&$waiting) {
        $waiting = true;

        while (true) {
            LoopPause::yield();
        }
    });

    $gone = clientOf($server);
    fwrite($gone, "GET /wait HTTP/1.1\r\nHost: h\r\n\r\n");
    turnUntil($this->loop, function () use (&$waiting) { return $waiting; });
    fclose($gone);
    turnUntil($this->loop, function () use (&$seen) { return $seen !== []; });

    expect($seen)->toBe([['/wait', 499]])
        ->and($reported)->toBe([]);
});

it('holds a pipelined request back unparsed while one is answered, so a bad one can\'t cut into it', function () {
    $server = serving($this);
    $this->app['router']->get('/slow', function () {
        LoopPause::yield();
        LoopPause::yield();

        return 'whole';
    });

    $answer = exchange($this->loop, clientOf($server), "GET /slow HTTP/1.1\r\nHost: h\r\n\r\nGET / HTTP/1.1\r\nHost: h\r\nX-Bad : v\r\n\r\n");
    $first = substr($answer, 0, strpos($answer, 'whole') + 5);

    expect(parsedAnswer($first)[0])->toBe(200)
        ->and(parsedAnswer($first)[2])->toBe('whole')
        ->and(substr($answer, strlen($first)))->toStartWith('HTTP/1.1 400 ');
});

it('keeps output the app writes outside its response out of the response, and reports it', function () {
    $server = serving($this);
    $reported = [];
    $this->app->registerInstance(ExceptionHandler::class, new class($reported) implements ExceptionHandler {
        public function __construct(private array &$reported) {}

        public function report(Throwable $e): void
        {
            $this->reported[] = $e->getMessage();
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function renderForConsole(OutputInterface $output, Throwable $e): void {}
    });
    $this->app['router']->get('/noisy', function () {
        echo 'debug noise';

        return 'clean';
    });

    $answer = parsedAnswer(exchange($this->loop, clientOf($server), "GET /noisy HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n"));

    expect($answer[2])->toBe('clean')
        ->and($reported)->toHaveCount(1)
        ->and($reported[0])->toContain('11 bytes of output outside its response to GET /noisy');
});

it('answers headers that take too long with 408', function () {
    $server = serving($this, ['header_timeout' => 0.5]);

    $answer = exchange($this->loop, clientOf($server), "GET / HTTP/1.1\r\nHost: h\r\n", timeout: 4.0);

    expect(parsedAnswer($answer)[0])->toBe(408);
});

it('announces each request after it is sent, and tells observers how it went', function () {
    $server = serving($this);
    $seen = [];
    $heard = [];
    $server->onRequest(function (ServedRequest $served) use (&$seen) {
        $seen[] = [$served->method, $served->path, $served->status];
    });
    $this->app['signals']->listen(RequestHandled::class, function (RequestHandled $event) use (&$heard) {
        $heard[] = $event->response->getStatusCode();
    });

    exchange($this->loop, clientOf($server), "GET /?q=1 HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");

    expect($seen)->toBe([['GET', '/', 200]])
        ->and($heard)->toBe([200]);
});

it('runs two servers on one loop', function () {
    $first = serving($this);
    $second = serving($this);

    expect($first->port())->not->toBe($second->port())
        ->and(parsedAnswer(exchange($this->loop, clientOf($first), "GET / HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n"))[2])->toBe('home')
        ->and(parsedAnswer(exchange($this->loop, clientOf($second), "GET / HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n"))[2])->toBe('home');
});

it('attaches to a loop already running, serves alongside its timer, and stop() takes it off again', function () {
    $ticks = 0;
    $answer = null;
    $port = null;
    $timer = $this->loop->every(0.05, function () use (&$ticks) { $ticks++; }, 'app.heartbeat');

    $this->loop->at(0.1, function () use (&$answer, &$port, &$ticks) {
        // Held until the end: only stop() may close its port, not the object going away.
        $server = $this->kept = new HttpServer($this->app, new ServerOptions(port: 0));
        $server->attach($this->loop);
        $port = $server->port();

        $this->loop->async(function () use ($server, &$answer, &$ticks) {
            $before = $ticks;
            $answer = exchange($this->loop, clientOf($server), "GET / HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
            $server->stop();
            $answer = [$answer, $before];
        });
    });

    $this->loop->until(function () use (&$answer, &$ticks) {
        return is_array($answer) && $ticks >= $answer[1] + 3;
    });
    $timer->cancel();
    $this->loop->forget('app.heartbeat');

    $refused = @stream_socket_client("tcp://127.0.0.1:{$port}", $code, $message, 1);

    expect(parsedAnswer($answer[0])[2])->toBe('home')
        ->and($ticks)->toBeGreaterThan($answer[1])
        ->and($refused)->toBeFalse();
});

it('drains: answers what is in flight, then settles once off the loop', function () {
    $server = serving($this);
    $this->app['router']->get('/slow', function () {
        LoopPause::yield();
        LoopPause::yield();

        return 'finished';
    });

    $started = false;
    $this->app['router']->get('/slower', function () use (&$started) {
        $started = true;
        LoopPause::yield();
        LoopPause::yield();

        return 'finished';
    });

    $socket = clientOf($server);
    fwrite($socket, "GET /slower HTTP/1.1\r\nHost: h\r\n\r\n");
    $pace = $this->loop->every(0.001, fn () => null, 'test.drain.pace');
    $this->loop->until(function () use (&$started) {
        return $started;
    });
    $pace->cancel();
    $this->loop->forget('test.drain.pace');

    $drained = false;
    $server->drain(5.0)->then(function () use (&$drained) { $drained = true; });

    $answer = exchange($this->loop, $socket, '');
    $this->loop->until(function () use (&$drained) {
        return $drained;
    });

    expect(parsedAnswer($answer)[2])->toBe('finished')
        ->and(parsedAnswer($answer)[1]['connection'])->toBe(['close'])
        ->and($drained)->toBeTrue();
});

it('closes its connections when the loop stops right as it drains, the server object kept', function () {
    $server = $this->kept = serving($this);
    $socket = clientOf($server);
    fwrite($socket, "GET / HTTP/1.1\r\nHost: h\r\n\r\n");
    exchange($this->loop, $socket, '', answers(1));

    // A runner that ends its loop the moment the server is off it, as serve and a sketch do.
    $server->drain(5.0)->then(fn () => $this->loop->stop(0));
    $this->loop->run();

    // Blocking, so the read waits up to the timeout for the close instead of returning before the FIN lands.
    stream_set_blocking($socket, true);
    stream_set_timeout($socket, 2);
    $rest = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);

    expect($rest)->toBe('')
        ->and($meta['timed_out'])->toBeFalse()
        ->and(feof($socket))->toBeTrue();
});
