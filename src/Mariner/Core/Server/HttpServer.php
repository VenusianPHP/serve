<?php

namespace Mariner\Core\Server;

use Closure;
use LogicException;
use Mariner\Contracts\Server\HttpServer as HttpServerContract;
use Mariner\Core\Http\Kernel;
use Mariner\Core\Server\Http1\HttpError;
use Mariner\Core\Server\Http1\ParsedRequest;
use Mariner\Http\Request;
use Mariner\View\FiberOutputBuffers;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\IOPools\CancelledException;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResources\Timer;
use Voyager\Contracts\IOPools\LoopResources\Wakeable;
use Voyager\Contracts\IOPools\Promise;
use Voyager\IOPools\Waiter\Wakes\Readable;
use Voyager\IOPools\Waiter\Wakes\Writable;

/**
 * Serves an app over HTTP/1.1 on an event loop it is given. It never makes, owns or runs a loop:
 * attach() puts its listener and connections on the loop as one resource, under a name of its
 * own (two servers can share a loop), and stop() or drain() takes them off again. Each request
 * runs in a loop task of its own, in a fresh scope, through the app's HTTP kernel.
 *
 * Nothing process-wide happens here: no signals, no console output, no workers. Whatever runs the
 * server (the serve command, a sketch) decides those; onRequest() tells it about each request.
 */
final class HttpServer implements HttpServerContract, Wakeable
{
    /** @var resource|null */
    private $listener = null;

    private ?int $port = null;

    private ?Loop $loop = null;

    private ?Timer $clock = null;

    /** @var array<int, Connection> by the stream's id */
    private array $connections = [];

    /** @var list<resource> streams to close once the waiter has let go of them */
    private array $closing = [];

    private int $in_flight = 0;

    private bool $accepting = false;

    private ?Promise $drained = null;

    /** drain()'s deadline, cancelled if everything is answered before it. */
    private ?Timer $deadline = null;

    /** @var list<Closure(ServedRequest): void> */
    private array $observers = [];

    private readonly string $name;

    /** The connection cap in force: ServerOptions::maxConnections(), settled once. */
    private readonly int $max_connections;

    public function __construct(
        private readonly FrameworkCore $app,
        public readonly ServerOptions $options = new ServerOptions,
    ) {
        $this->name = 'serve.'.spl_object_id($this);
        $this->max_connections = $options->maxConnections();
    }

    /**
     * Bind the listening socket.
     *
     * @throws RuntimeException  the address can't be listened on
     */
    public function listen(): static
    {
        if (! is_null($this->listener)) {
            return $this;
        }

        $context = stream_context_create(['socket' => ['so_reuseport' => $this->options->reuse_port, 'backlog' => 511]]);
        $address = "tcp://{$this->options->host}:{$this->options->port}";
        $listener = @stream_socket_server($address, $code, $message, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if ($listener === false) {
            throw new RuntimeException("Could not listen on {$address}: {$message}");
        }

        stream_set_blocking($listener, false);
        $this->listener = $listener;
        $this->port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);

        return $this;
    }

    /** The connections held at once, at most: as set, or as the open-file limit allows. */
    public function maxConnections(): int
    {
        return $this->max_connections;
    }

    /** The port listened on: the one the system picked when the options asked for 0. */
    public function port(): ?int
    {
        return $this->port;
    }

    /** Serve on $loop, from its next turn. */
    public function attach(Loop $loop): static
    {
        if (! is_null($this->loop)) {
            throw new LogicException('This server is already attached to a loop.');
        }

        $this->listen();
        $this->loop = $loop;
        $this->accepting = true;
        $loop->resource($this->name, $this);
        $this->clock = $loop->every(1.0, $this->enforceTimeouts(...), "{$this->name}.clock");

        return $this;
    }

    /** Tell $observer about each request once it is answered. */
    public function onRequest(Closure $observer): static
    {
        $this->observers[] = $observer;

        return $this;
    }

    /**
     * Stop accepting connections, close the idle ones, and leave the loop once the requests in
     * flight are answered. drain() is the same, as a promise with a deadline.
     */
    public function stop(): void
    {
        if (is_null($this->loop)) {
            return;
        }

        $this->accepting = false;

        if (! is_null($this->listener)) {
            $this->closing[] = $this->listener;
            $this->listener = null;
        }

        foreach ($this->connections as $connection) {
            if ($connection->idle()) {
                $this->close($connection);
            } else {
                $connection->closing = true;
            }
        }

        $this->detachWhenDone();
    }

    /**
     * stop(), then wait up to $timeout_s for the requests in flight; whatever is left then closes.
     *
     * @return Promise<null>  settled once the server is off the loop
     */
    public function drain(float $timeout_s = 30.0): Promise
    {
        if (is_null($this->loop)) {
            throw new LogicException('This server is not attached to a loop.');
        }

        $loop = $this->loop;
        $drained = $this->drained ??= $loop->promise();
        $this->stop();

        // stop() detaches at once when nothing is left to answer, settling the promise.
        if (! is_null($this->loop)) {
            $this->deadline = $loop->at($timeout_s, function () {
                $this->deadline = null;

                foreach ($this->connections as $connection) {
                    $this->close($connection);
                }

                $this->detachWhenDone();
            });
        }

        return $drained;
    }

    public function wakes(): array
    {
        // Closed only now: the waiter dropped them when it synced after the last wakes().
        foreach ($this->closing as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->closing = [];
        $wakes = [];

        // At either cap the listener goes quiet: new clients wait in the backlog, not accepted to be dropped.
        if ($this->accepting && ! is_null($this->listener) && $this->in_flight < $this->options->max_in_flight
            && count($this->connections) < $this->max_connections) {
            $wakes[] = new Readable($this->listener);
        }

        foreach ($this->connections as $connection) {
            if ($connection->wantsRead($this->options->max_buffered)) {
                $wakes[] = new Readable($connection->stream);
            }

            if ($connection->wantsWrite()) {
                $wakes[] = new Writable($connection->stream);
            }
        }

        return $wakes;
    }

    public function woke(array $fired): void
    {
        foreach ($fired as $wake) {
            if ($wake->stream === $this->listener) {
                $this->accept();

                continue;
            }

            $connection = $this->connections[(int) $wake->stream] ?? null;

            if (is_null($connection) || $connection->closed) {
                continue;
            }

            $alive = $wake instanceof Writable ? $connection->write() : $connection->read();

            if (! $alive || ($connection->closing && ! $connection->wantsWrite() && ! $connection->busy)) {
                $this->close($connection);
            }
        }

        $this->dispatch();
        $this->detachWhenDone();
    }

    private function accept(): void
    {
        while ($this->accepting && count($this->connections) < $this->max_connections
            && ($stream = @stream_socket_accept($this->listener, 0, $remote)) !== false) {
            stream_set_blocking($stream, false);
            $this->connections[(int) $stream] = new Connection($stream, (string) $remote, $this->options->limits);
        }
    }

    /** Start the next request of each connection whose turn it is, while there is room. */
    private function dispatch(): void
    {
        foreach ($this->connections as $connection) {
            if ($this->in_flight >= $this->options->max_in_flight) {
                return;
            }

            if (! is_null($request = $connection->next($this->options->max_buffered))) {
                $this->in_flight++;
                $connection->busy = true;
                $connection->serving = $request;
                $task = $this->loop->async(fn () => $this->serve($connection, $request));

                // async() runs the request until it first waits: it may have finished, and the next
                // one begun, before the task comes back. Only a request still being answered keeps it.
                if ($connection->serving === $request) {
                    $connection->task = $task;
                }
            }
        }
    }

    /** Answer one request, in its own loop task and scope. */
    private function serve(Connection $connection, ParsedRequest $parsed): void
    {
        $began = microtime(true);
        $status = 0;
        $factory = new RequestFactory($this->options->host, (int) $this->port, $this->options->https);

        try {
            $this->app->forgetScopedInstances();

            if (! is_null($this->options->health) && strtok($parsed->target, '?') === $this->options->health) {
                $status = 200;
                $this->send($connection, $parsed, new Response('OK', 200, ['Content-Type' => 'text/plain; charset=UTF-8']));

                return;
            }

            if (! is_null($file = $this->publicFile($parsed))) {
                $response = new BinaryFileResponse($file, 200, $this->fileType($file), true, null, false, true);
                $response->prepare($request = $factory->make($parsed, $connection->remote));
                $status = $response->getStatusCode();
                $this->send($connection, $parsed, $response, $request);

                return;
            }

            try {
                $request = $factory->make($parsed, $connection->remote);
            } catch (HttpError $e) {
                $status = $e->status;
                $connection->refuse($e->status, $e->getMessage());

                return;
            }

            $kernel = $this->app->make(Kernel::class);
            $response = $this->handle($kernel, $request);
            $status = $response->getStatusCode();
            $this->send($connection, $parsed, $response, $request);
            $this->terminate($kernel, $request, $response);
        } catch (CancelledException) {
            // The client went away mid-request (or the drain deadline passed): nobody to answer.
            $status = 499;
        } finally {
            foreach ($factory->uploads() as $path) {
                @unlink($path);
            }

            $this->in_flight--;
            [$connection->busy, $connection->serving, $connection->task] = [false, null, null];

            foreach ($this->observers as $observer) {
                $observer(new ServedRequest($parsed->method, strtok($parsed->target, '?'), $status, microtime(true) - $began));
            }

            $this->dispatch();
            $this->detachWhenDone();
        }
    }

    /**
     * The file under public/ a GET or HEAD asks for, or null: never outside public/ (no "..", no
     * symlink out), never a directory, never a .php file, which the app answers instead.
     */
    /**
     * The type a web server gives $file, from its extension: guessing from content calls CSS and JS plain text.
     *
     * @return array<string, string>
     */
    private function fileType(string $file): array
    {
        $type = MimeTypes::getDefault()->getMimeTypes(strtolower(pathinfo($file, PATHINFO_EXTENSION)))[0] ?? null;

        return is_null($type) ? [] : ['Content-Type' => str_starts_with($type, 'text/') ? "{$type}; charset=UTF-8" : $type];
    }

    private function publicFile(ParsedRequest $parsed): ?string
    {
        if (! $this->options->static || ! in_array($parsed->method, ['GET', 'HEAD'], true) || ! $this->app->isBound('path.public')) {
            return null;
        }

        $path = rawurldecode((string) parse_url($parsed->target, PHP_URL_PATH));

        if ($path === '' || $path === '/' || str_contains($path, "\0") || str_ends_with(strtolower($path), '.php')) {
            return null;
        }

        $public = rtrim($this->app->make('path.public'), '/');

        // Inside a packaged app public/ is in the phar: realpath() can't resolve phar:// paths, and a
        // phar has no symlinks, so a path without "." or ".." segments is all that stays inside it.
        if (str_starts_with($public, 'phar://')) {
            $segments = explode('/', trim($path, '/'));

            return array_intersect($segments, ['.', '..', '']) === [] && is_file($file = $public.'/'.implode('/', $segments)) ? $file : null;
        }

        $root = realpath($public);
        $file = $root === false ? false : realpath($root.$path);

        if ($file === false || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }

        return $file;
    }

    /** The kernel's response, with any output the app wrote outside it captured and reported. */
    private function handle(Kernel $kernel, Request $request): Response
    {
        ob_start();

        try {
            $response = $kernel->handle($request);
        } finally {
            $stray = ob_get_clean();
        }

        if ($stray !== '' && $stray !== false) {
            $this->report(new RuntimeException(sprintf(
                'The app wrote %d bytes of output outside its response to %s %s; a server sends only the response.',
                strlen($stray), $request->method(), $request->getRequestUri(),
            )));
        }

        return $response;
    }

    private function terminate(Kernel $kernel, Request $request, Response $response): void
    {
        try {
            $kernel->terminate($request, $response);
        } catch (Throwable $e) {
            $this->report($e);
        }
    }

    /** Queue the response on the connection: whole, a file part by part, or streamed as it is made. */
    private function send(Connection $connection, ParsedRequest $parsed, Response $response, ?Request $request = null): void
    {
        $keep_alive = $parsed->keepAlive() && $this->accepting
            && strtolower((string) $response->headers->get('Connection')) !== 'close';

        if (! is_null($request)) {
            $response->prepare($request);
        }

        if ($response instanceof StreamedResponse && $parsed->method !== 'HEAD') {
            $this->stream($connection, $parsed, $response, $keep_alive);
        } else {
            [$head, $body] = ResponseWriter::write($response, $parsed->version, $keep_alive, $parsed->method === 'HEAD');
            $connection->queue($head);
            $connection->queue($body);
        }

        if (! $keep_alive) {
            $connection->closing = true;
        }
    }

    /**
     * A streamed response, its output sent as it is made: chunked over HTTP/1.1, and over 1.0
     * delimited by closing the connection.
     */
    private function stream(Connection $connection, ParsedRequest $parsed, StreamedResponse $response, bool $keep_alive): void
    {
        $chunked = $parsed->version === '1.1';
        $keep_alive = $keep_alive && $chunked;

        $connection->queue(ResponseWriter::head($response, $parsed->version, $keep_alive, null, $chunked));

        FiberOutputBuffers::start(function (string $bytes) use ($connection, $chunked) {
            if ($bytes !== '') {
                $connection->queue($chunked ? dechex(strlen($bytes))."\r\n{$bytes}\r\n" : $bytes);
            }

            return '';
        }, 1);

        try {
            $response->sendContent();
        } finally {
            FiberOutputBuffers::end();
        }

        if ($chunked) {
            $connection->queue("0\r\n\r\n");
        }

        if (! $keep_alive) {
            $connection->closing = true;
        }
    }

    /**
     * Answer a head that takes too long, or a body that stops arriving, with 408; close a connection
     * whose answer has stopped leaving, and an idle keep-alive one.
     */
    private function enforceTimeouts(): void
    {
        $now = microtime(true);

        foreach ($this->connections as $connection) {
            if ($connection->wantsWrite() && $now - $connection->last_write > $this->options->write_timeout) {
                $this->close($connection);

                continue;
            }

            if ($connection->busy || $connection->closing) {
                continue;
            }

            if ($connection->parser->readingBody()) {
                if ($now - $connection->last_activity > $this->options->body_timeout) {
                    $connection->refuse(408, 'The request body stopped arriving.');
                }
            } elseif (! is_null($connection->request_began) && $now - $connection->request_began > $this->options->header_timeout) {
                $connection->refuse(408, 'The request head took too long to arrive.');
            } elseif ($connection->idle() && $now - $connection->last_activity > $this->options->idle_timeout) {
                $this->close($connection);
            }
        }

        $this->detachWhenDone();
    }

    /** Release the connection, and cancel the request it was answering: its handler's finally runs now. */
    private function close(Connection $connection): void
    {
        unset($this->connections[(int) $connection->stream]);
        $connection->release();
        $this->closing[] = $connection->stream;
        $connection->task?->cancel();
    }

    /** Once stopped with nothing left to answer, leave the loop. */
    private function detachWhenDone(): void
    {
        if ($this->accepting || is_null($this->loop) || $this->in_flight > 0) {
            return;
        }

        foreach ($this->connections as $connection) {
            if ($connection->wantsWrite() || $connection->busy) {
                return;
            }

            $this->close($connection);
        }

        $this->clock?->cancel();
        $this->deadline?->cancel();
        $this->deadline = null;
        $this->loop->forget("{$this->name}.clock");
        $this->loop->forget($this->name);

        // Closed a turn later, once the waiter has let go of them along with this resource.
        $streams = $this->closing;
        $this->closing = [];
        $this->loop->at(0.0, function () use ($streams) {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        });

        $this->loop = null;
        $this->clock = null;
        $this->drained?->resolve(null);
        $this->drained = null;
    }

    private function report(Throwable $e): void
    {
        try {
            $this->app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable) {
            error_log('Mariner serve could not report: '.$e->getMessage());
        }
    }
}
