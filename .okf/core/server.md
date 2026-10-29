---
type: Module
title: Server
description: "php computer serve and HttpServer: an HTTP/1.1 server attached to a loop it is given, its workers, and what threads share."
resource: src/Mariner/Core/Server
tags: [core, server, serve, http1, workers, threads]
status: draft
generated: { by: claude-sonnet-5-5/claude-code, at: 2026-10-28T00:00:00Z }
sources:
  - id: parser
    resource: git:4e2090e
    title: Add an HTTP/1.1 request parser for php computer serve
  - id: requests
    resource: git:84f5692
    title: Build Mariner requests from requests off the wire
  - id: server
    resource: git:e1a71aa
    title: Add HttpServer
  - id: command
    resource: git:697a0dc
    title: Add php computer serve
  - id: workers
    resource: git:e5643c7
    title: Drain on the first stop signal, and run several workers on Linux
  - id: inflight
    resource: tests/Core/Server/HttpServerTest.php
    title: Several requests at once by default, on every OS
  - id: contract
    resource: src/Mariner/Contracts/Server/HttpServer.php
    title: Server\HttpServer, the contract
  - id: contract-tests
    resource: tests/Core/Server/HttpServerContractTest.php
    title: The HttpServer contract resolved, attached and drained
  - id: cancel
    resource: tests/Core/Server/HttpServerTest.php
    title: A client that hangs up cancels its request
  - id: sketch
    resource: tests/Core/SketchServeTest.php
    title: Serving from a sketch (Rocket end-to-end tests)
  - id: php-env-lock
    resource: https://github.com/php/php-src/blob/PHP-8.4/ext/standard/basic_functions.c
    title: php-src PHP-8.4 basic_functions.c (php_getenv, PHP_FUNCTION(putenv))
---

# Overview

`Mariner\Core\Server\HttpServer` serves an app over HTTP/1.1 on an event loop it is given and never owns: `listen()`, `attach($loop)`, `stop()`, `drain($timeout)` (a promise), `onRequest()` for observers. Its listener and connections are one loop resource under a name of its own, so two servers share a loop and a running app (a sketch) can attach one mid-run. Each request runs in its own loop task and fresh scope through `Mariner\Core\Http\Kernel`. Up to `max_in_flight` of them (10 by default, on every OS) run at once, so a held stream or a request waiting on another's answer doesn't stop the rest; 1 answers them one at a time in arrival order.[^inflight] A connection is still read while its request is answered, so a client that hangs up is seen (or the next write fails): the request's task is cancelled, `CancelledException` lands where the handler waits, its `finally` runs, its place is freed and observers see status 499. `ExceptionRenderer::handle()` rethrows that exception rather than reporting or rendering it. Bytes a client sends meanwhile (a pipelined request) stay unparsed until their turn, up to 64 KiB, so a refusal can't cut into the answer being sent.[^cancel] Nothing process-wide happens in it: signals, console output and workers belong to whoever runs it.[^server]

It implements `Mariner\Contracts\Server\HttpServer`, which declares `listen()`, `port()`, `attach($loop)`, `onRequest()`, `stop()` and `drain()` and nothing else (`wakes()`, `woke()` and `maxConnections()` stay with the loop and the options), so a package in the `venusian-mariner/contracts` split can serve without requiring `venusian/serve`. The container binds the contract to the same factory as the class.[^contract]

`Http1\Parser` reads requests as bytes arrive and refuses the framing ambiguities request smuggling relies on, with a status for each limit.[^parser] `RequestFactory` and `Http1\Multipart` build the Mariner request as PHP's SAPI would; uploads are framework `UploadedFile`s in test mode, which is safe only because every path is a temporary file the server created, never client input.[^requests]

Before the app, the server answers `serve.health` (`/up`, `OK`) and, with `serve.static` on (the default), GET and HEAD for real files under `public/`: typed by extension, with `Range`. It never serves `.php` files, directories, or anything whose real path leaves `public/` (`../`, `%2e%2e`, symlinks out); those go to the app. Under FPM, nginx serves the files and the app's own `/up` route answers health.

# php computer serve

`ServeCommand` reads `config/serve.php` (flags override it), attaches a server to the app's loop and runs it. One console line per request on a terminal or with `-v`; `--quiet` silences it. The first Ctrl-C or SIGTERM (`Loop::onStopSignal`) drains within `serve.drain_timeout`, a second ends the process.[^command][^workers]

`--workers N` runs N `serve:worker` processes on one port through `SO_REUSEPORT` (Linux only; elsewhere it refuses). `Supervisor` starts them with the console's output (lines prefixed `[i]`), restarts one that dies with a growing pause, forwards stop signals so each drains, and gives up on a worker that dies more than 5 times in 60 s, exiting 1 with the reason.[^workers]

# Serving from a sketch

A running sketch (a `php rocket` app, a venusian/build binary) serves its own app over HTTP on its own loop: the container makes an `HttpServer` for this app (`config/serve.php`, or `make(HttpServer::class, ['options' => new ServerOptions(...)])`; a package that cannot name `Mariner\Core` makes `Mariner\Contracts\Server\HttpServer` with `['options' => ['port' => 0]]`, and the keys are laid over `config/serve.php` through `ServerOptions::fromConfig()`[^contract-tests]), and the routes, services and state are the sketch's, with no second boot. The sketch decides when the server starts and stops; requests run in their own scope, so the sketch's own scoped instances are untouched.[^sketch]

```php
use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Voyager\Contracts\Sketches\SketchLoopResult;
use Voyager\Sketches\Sketch;

final class Dashboard extends Sketch
{
    private ?HttpServer $server = null;

    private bool $done = false;

    public function boot(): void
    {
        $loop = app('event-loop');

        $this->server = app(HttpServer::class, ['options' => new ServerOptions(port: 8080)])
            ->listen()
            ->attach($loop);

        // Ctrl-C: answer what is in flight, then stop; shutdown() still runs afterwards.
        $loop->onStopSignal(function () {
            $this->server->drain(5.0)->then(fn () => $this->done = true);

            return true;
        });
    }

    public function loop(array $mail = []): SketchLoopResult
    {
        // ... the sketch's own work, every frame ...

        return $this->done ? SketchLoopResult::STOP : SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        // the sketch's own cleanup
    }
}
```

To stop on its own terms instead, the sketch calls `$this->server->drain(5.0)->then(...)` from `loop()` and returns `STOP` once it settles.

# Threads: what workers in one process share

Thread workers (a `parallel\Runtime` each) are separate PHP interpreters in one process: PHP statics, INI settings and module globals are per thread; what the C runtime keeps per process is shared.

| State | Touched by | Under ZTS | Safe with threads booting at once |
|---|---|---|---|
| Environment (`putenv`/`getenv`) | Dotenv through `Env`'s `PutenvAdapter` (immutable), on boot | Process-wide, but PHP's own `getenv()` and `putenv()` take TSRM's env lock[^php-env-lock] | Yes for PHP code; the supervisor loads `.env` before any thread starts, so the threads' immutable loads find every variable set and write nothing |
| `error_reporting`, `display_errors` | `HandleExceptions`, on boot | Per-thread INI | Yes |
| Default timezone | `LoadConfiguration` | Per-thread | Yes |
| `mb_internal_encoding` | `LoadConfiguration` | Per-thread | Yes |
| Working directory | Nothing | Virtual cwd per thread | N/A |
| `umask` | Read only (`Filesystem::replace`) | Process-wide | Yes: never set |
| `setlocale` | Nothing in framework or serve | Process-wide | N/A |
| Signal dispositions | A loop's stop-signal watching (kqueue sets `SIG_IGN`, then `SIG_DFL` on teardown; `SignalRelay` installs a handler, then `SIG_DFL`) | Process-wide | No: a thread's loop takes SIGTERM from the supervisor. Thread loops must not watch stop signals (framework mechanism pending) |

Handling a request calls none of the setters above.

# Open items

* Thread workers: waiting on a framework way for a loop to stay out of stop signals, and on restoring the previous disposition when a loop stops watching (both with Angel). The fatal-error limit: a PHP fatal inside a thread ends the whole process; process workers contain it.
* Thread worker code (ThreadWorker, ThreadServe, `--driver`) is written and waits on the loop mechanism above before it is committed.

# Per-request state (what Octane resets)

Nothing a request changes outlives it. Mariner's static properties are boot-time configuration (callbacks, flags, metadata caches), and the facade resolves from the container on every call. The services kept for the whole process hold configuration only; what belongs to one request lives in a scoped object each request gets fresh, since every request runs in a new scope (`forgetScopedInstances()`):

| Service (process-wide) | Its per-request state (scoped) |
|---|---|
| `cookie` (CookieJar) | `CookieQueue` |
| `view` (Factory), `Vite` | `RenderState`, `ViteState` |
| `session` (SessionManager) | `SessionState`, `session.store` |
| `auth` (AuthManager) | `GuardState`, `auth.driver` |
| `translator` | `LocaleState` |
| `mail.manager` (MailManager) | none: it caches mailers built from config, whose global from, reply-to and to addresses are config too |
| `Notifications\ChannelManager` | none: `locale()` returns a copy carrying the locale, so the shared manager never holds one request's |
| `request`, `url`, `redirect` | scoped themselves |

[^server]: Add HttpServer
[^parser]: Add an HTTP/1.1 request parser for php computer serve
[^requests]: Build Mariner requests from requests off the wire
[^command]: Add php computer serve
[^workers]: Drain on the first stop signal, and run several workers on Linux
[^inflight]: Several requests at once by default, on every OS
[^contract]: Server\HttpServer, the contract
[^contract-tests]: The HttpServer contract resolved, attached and drained
[^cancel]: A client that hangs up cancels its request
[^sketch]: Serving from a sketch (Rocket end-to-end tests)
[^php-env-lock]: php-src PHP-8.4 basic_functions.c (php_getenv, PHP_FUNCTION(putenv))
