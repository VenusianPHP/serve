<?php

namespace Mariner\Core\Server;

use InvalidArgumentException;
use Mariner\Core\Server\Http1\Limits;

/** How an HttpServer listens and what it accepts. */
final readonly class ServerOptions
{
    public function __construct(
        public string $host = '127.0.0.1',
        /** 0 lets the system pick a free port: HttpServer::port() says which. */
        public int $port = 8020,
        /** Requests handled at once. Above 1 only on Linux. */
        public int $max_in_flight = 1,
        public Limits $limits = new Limits,
        /** Seconds a client may take to send a request's line and headers: then 408. */
        public float $header_timeout = 30.0,
        /** Seconds a request body may go without a byte arriving: then 408. A slow but steady upload is fine. */
        public float $body_timeout = 30.0,
        /** Seconds a response may go without a byte leaving: then the connection is closed. */
        public float $write_timeout = 30.0,
        /**
         * Connections held at once; beyond it new ones wait in the system's backlog until one closes.
         * Null: 1024, or fewer when the process may not open that many files (see maxConnections()).
         */
        public ?int $max_connections = null,
        /** Bytes of answers queued for one connection beyond which its requests stop being read. */
        public int $max_buffered = 1_048_576,
        /** Seconds an idle keep-alive connection stays open. */
        public float $idle_timeout = 60.0,
        /** Serving behind a TLS proxy: requests are https. */
        public bool $https = false,
        /** The health check's path, answered 200 "OK" without the app; null for none. */
        public ?string $health = '/up',
        /** Bind with SO_REUSEPORT, so workers share the port. Linux only. */
        public bool $reuse_port = false,
        /** Answer GET and HEAD for files under public/ (never .php) without the app. */
        public bool $static = true,
    ) {
        if ((! is_null($max_connections) && $max_connections < 1) || $max_buffered < 1) {
            throw new InvalidArgumentException('max_connections and max_buffered must be at least 1.');
        }

        if (! is_null($max_connections) && ! is_null($room = self::fileRoom()) && $max_connections > $room) {
            throw new InvalidArgumentException(sprintf(
                'max_connections %d is more than this process can open: its soft file limit leaves room for %d connections (RLIMIT_NOFILE minus %d for the app\'s own files). Raise the limit (ulimit -n) or lower the setting.',
                $max_connections, $room, self::FILE_RESERVE,
            ));
        }

        if ($max_in_flight < 1) {
            throw new InvalidArgumentException('max_in_flight must be at least 1.');
        }

        if ($max_in_flight > 1 && PHP_OS_FAMILY !== 'Linux') {
            throw new InvalidArgumentException("max_in_flight above 1 needs Linux; this is ".PHP_OS_FAMILY.'.');
        }

        if ($reuse_port && PHP_OS_FAMILY !== 'Linux') {
            throw new InvalidArgumentException('Sharing a port between workers (SO_REUSEPORT balancing) needs Linux; this is '.PHP_OS_FAMILY.'.');
        }
    }

    /** Files kept back from the connection cap for the app's own: its files, database, logs. */
    public const int FILE_RESERVE = 64;

    /** The connections the server holds at once: as set, or 1024 within what the process may open. */
    public function maxConnections(): int
    {
        if (! is_null($this->max_connections)) {
            return $this->max_connections;
        }

        $room = self::fileRoom();

        return is_null($room) ? 1024 : max(1, min(1024, $room));
    }

    /** Connections the process's soft open-file limit leaves room for, or null when it has none known. */
    public static function fileRoom(): ?int
    {
        $limit = function_exists('posix_getrlimit') ? (posix_getrlimit()['soft openfiles'] ?? null) : null;

        return is_numeric($limit) ? (int) $limit - self::FILE_RESERVE : null;
    }

    /** The options config/serve.php names, over these defaults. */
    public static function fromConfig(array $config): self
    {
        return new self(
            host: (string) ($config['host'] ?? '127.0.0.1'),
            port: (int) ($config['port'] ?? 8020),
            max_in_flight: (int) ($config['max_in_flight'] ?? 1),
            limits: new Limits(
                request_line: (int) ($config['limits']['request_line'] ?? 8_192),
                header_bytes: (int) ($config['limits']['header_bytes'] ?? 16_384),
                headers: (int) ($config['limits']['headers'] ?? 100),
                body: (int) ($config['limits']['body'] ?? 10_485_760),
            ),
            header_timeout: (float) ($config['header_timeout'] ?? 30.0),
            body_timeout: (float) ($config['body_timeout'] ?? 30.0),
            write_timeout: (float) ($config['write_timeout'] ?? 30.0),
            max_connections: isset($config['max_connections']) ? (int) $config['max_connections'] : null,
            max_buffered: (int) ($config['max_buffered'] ?? 1_048_576),
            idle_timeout: (float) ($config['idle_timeout'] ?? 60.0),
            https: (bool) ($config['https'] ?? false),
            health: array_key_exists('health', $config) ? $config['health'] : '/up',
            reuse_port: (bool) ($config['reuse_port'] ?? false),
            static: (bool) ($config['static'] ?? true),
        );
    }
}
