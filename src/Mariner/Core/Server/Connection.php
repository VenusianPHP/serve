<?php

namespace Mariner\Core\Server;

use Mariner\Core\Server\Http1\HttpError;
use Mariner\Core\Server\Http1\Limits;
use Mariner\Core\Server\Http1\ParsedRequest;
use Mariner\Core\Server\Http1\Parser;

/**
 * One client's socket: bytes in through the parser, one request answered at a time (pipelined
 * ones wait their turn), bytes out as the socket can take them.
 */
final class Connection
{
    private const int READ = 65_536;

    private const int WRITE = 65_536;

    public readonly Parser $parser;

    /** @var list<ParsedRequest> parsed and waiting for their turn */
    private array $waiting = [];

    /** @var list<string|FileBody> */
    private array $outbox = [];

    /** A request of this connection is being handled. */
    public bool $busy = false;

    /** Close once everything queued is written. */
    public bool $closing = false;

    public bool $closed = false;

    /** When the head of the request now arriving began to, or null between requests. */
    public ?float $request_began = null;

    public float $last_activity;

    /** When bytes last left the outbox, or the outbox last went from empty to not. */
    public float $last_write;

    /** Bytes of strings queued to write. */
    private int $buffered = 0;

    /**
     * @param  resource  $stream
     */
    public function __construct(
        public readonly mixed $stream,
        public readonly string $remote,
        Limits $limits,
    ) {
        $this->parser = new Parser($limits);
        $this->last_activity = $this->last_write = microtime(true);
    }

    /**
     * Whether to read more: not while a request waits, nor once the connection is ending, nor while
     * more than $max_buffered bytes of answers wait for a client that isn't reading them.
     */
    public function wantsRead(int $max_buffered): bool
    {
        return ! $this->closed && ! $this->closing && ! $this->busy && $this->waiting === [] && $this->buffered <= $max_buffered;
    }

    public function buffered(): int
    {
        return $this->buffered;
    }

    public function wantsWrite(): bool
    {
        return ! $this->closed && $this->outbox !== [];
    }

    /** Whether nothing is queued, in flight or arriving: the connection is idle between requests. */
    public function idle(): bool
    {
        return ! $this->busy && $this->waiting === [] && $this->outbox === [] && ! $this->parser->midRequest();
    }

    /**
     * Read what arrived, and parse the requests it completes.
     *
     * @return bool  false when the client closed its end
     */
    public function read(): bool
    {
        $bytes = @fread($this->stream, self::READ);

        if ($bytes === false || ($bytes === '' && feof($this->stream))) {
            return false;
        }

        $this->last_activity = microtime(true);
        $this->parser->push($bytes);
        $this->parse();

        return true;
    }

    /**
     * The next request to answer, if it is this connection's turn: not while more than $max_buffered
     * bytes of answers are still waiting for the client to read them.
     */
    public function next(int $max_buffered): ?ParsedRequest
    {
        if ($this->busy || $this->closing || $this->buffered > $max_buffered) {
            return null;
        }

        $this->parse();

        return array_shift($this->waiting);
    }

    /**
     * Take the next whole request off the parser, one at a time: pipelined requests stay raw bytes
     * in the parser until their turn, never piled up parsed.
     */
    private function parse(): void
    {
        if ($this->waiting === [] && ! $this->closing) {
            try {
                if ($request = $this->parser->next()) {
                    $this->waiting[] = $request;
                }
            } catch (HttpError $e) {
                $this->refuse($e->status, $e->getMessage());
            }
        }

        if ($this->parser->takeContinue()) {
            $this->queue("HTTP/1.1 100 Continue\r\n\r\n");
        }

        // The head's clock runs from its first byte until it is read; the body's is last_activity.
        if (! $this->parser->midRequest() || $this->parser->readingBody()) {
            $this->request_began = null;
        } else {
            $this->request_began ??= $this->last_activity;
        }
    }

    public function queue(string|FileBody $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        if ($this->outbox === []) {
            $this->last_write = microtime(true);
        }

        $this->outbox[] = $bytes;
        $this->buffered += is_string($bytes) ? strlen($bytes) : 0;
    }

    /** Answer with an error and close: what follows can't be trusted to start a request. */
    public function refuse(int $status, string $reason): void
    {
        $text = \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error';
        $body = "{$status} {$text}\n";

        $this->waiting = [];
        $this->queue("HTTP/1.1 {$status} {$text}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n{$body}");
        $this->closing = true;
    }

    /**
     * Write what the socket takes now.
     *
     * @return bool  false when the client is gone
     */
    public function write(): bool
    {
        while ($this->outbox !== []) {
            $next = $this->outbox[0];

            if ($next instanceof FileBody) {
                $chunk = $next->read(self::WRITE);

                if ($chunk === '') {
                    $next->close();
                    array_shift($this->outbox);

                    continue;
                }

                // Put the chunk in front of the file, so a partial write keeps its order.
                array_unshift($this->outbox, $chunk);
                $this->buffered += strlen($chunk);

                continue;
            }

            $written = @fwrite($this->stream, $next);

            if ($written === false) {
                return false;
            }

            if ($written === 0) {
                return true;  // the socket is full: the next Writable resumes here
            }

            $this->last_activity = $this->last_write = microtime(true);
            $this->buffered -= $written;

            if ($written < strlen($next)) {
                $this->outbox[0] = substr($next, $written);

                return true;
            }

            array_shift($this->outbox);
        }

        return true;
    }

    /** Release what the connection holds; the stream itself is closed by the server. */
    public function release(): void
    {
        foreach ($this->outbox as $queued) {
            if ($queued instanceof FileBody) {
                $queued->close();
            }
        }

        [$this->outbox, $this->waiting, $this->closed, $this->buffered] = [[], [], true, 0];
    }
}
