<?php

namespace Mariner\Core\Server\Http1;

/**
 * Reads HTTP/1.1 requests (and 1.0) off one connection, bytes as they arrive. push() what was
 * read, then take each complete request from next() until it returns null for more bytes.
 * Pipelined requests come out one after another. A request the parser refuses throws HttpError;
 * the connection answers it and closes.
 *
 * It refuses what lets two parsers disagree about where a request ends (Content-Length beside
 * Transfer-Encoding, Content-Length values that differ, folded or malformed header lines, bare
 * line feeds), so a proxy in front can't smuggle a second request past it.
 */
final class Parser
{
    private const string TOKEN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    private const int CHUNK_LINE = 1_024;

    private string $buffer = '';

    private State $state = State::Head;

    private ?string $method = null;

    private ?string $target = null;

    private ?string $version = null;

    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @var resource|null */
    private $body = null;

    /** Body bytes still to read: of the whole body, or of the current chunk. */
    private int $remaining = 0;

    private int $received = 0;

    private bool $continue = false;

    public function __construct(private readonly Limits $limits = new Limits) {}

    public function push(string $bytes): void
    {
        $this->buffer .= $bytes;
    }

    /** Bytes pushed and not yet taken as part of a request. */
    public function pending(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Whether the request being read asked for "100 Continue" before sending its body. True
     * once per request: the connection writes the interim response when it sees it.
     */
    public function takeContinue(): bool
    {
        [$continue, $this->continue] = [$this->continue, false];

        return $continue;
    }

    /** Whether the head is read and the body is still arriving. */
    public function readingBody(): bool
    {
        return $this->state !== State::Head && $this->state !== State::Done;
    }

    /** Whether bytes of an unfinished request are buffered: a connection closing now cut it off. */
    public function midRequest(): bool
    {
        return $this->state !== State::Head || ltrim($this->buffer, "\r\n") !== '';
    }

    /**
     * The next complete request, or null until more bytes arrive.
     *
     * @throws HttpError
     */
    public function next(): ?ParsedRequest
    {
        while (true) {
            $progressed = match ($this->state) {
                State::Head => $this->readHead(),
                State::Body => $this->readBody(),
                State::ChunkSize => $this->readChunkSize(),
                State::ChunkData => $this->readChunkData(),
                State::ChunkEnd => $this->readChunkEnd(),
                State::Trailers => $this->readTrailers(),
                State::Done => true,
            };

            if ($this->state === State::Done) {
                return $this->finish();
            }

            if (! $progressed) {
                return null;
            }
        }
    }

    private function readHead(): bool
    {
        // A client may send stray line breaks between requests; they start nothing.
        $this->buffer = ltrim($this->buffer, "\r\n");

        $end = strpos($this->buffer, "\r\n\r\n");

        if ($end === false) {
            $line_end = strpos($this->buffer, "\r\n");

            if ($line_end === false && strlen($this->buffer) > $this->limits->request_line) {
                throw new HttpError(414, 'The request line is too long.');
            }

            if (strlen($this->buffer) > $this->limits->header_bytes) {
                throw new HttpError(431, 'The request headers are too large.');
            }

            $this->refuseBareLineBreaks($this->buffer);

            return false;
        }

        if ($end > $this->limits->header_bytes) {
            throw new HttpError(431, 'The request headers are too large.');
        }

        $head = substr($this->buffer, 0, $end);
        $this->buffer = substr($this->buffer, $end + 4);

        $this->refuseBareLineBreaks($head);

        $lines = explode("\r\n", $head);

        $this->readRequestLine(array_shift($lines));
        $this->readHeaders($lines);
        $this->frameBody();

        return true;
    }

    /**
     * Lines end in CRLF and nothing else: a lone LF or CR is where two parsers would disagree.
     * A CR at the very end of what has arrived may still be followed by its LF.
     */
    private function refuseBareLineBreaks(string $head): void
    {
        if (preg_match('/(?<!\r)\n|\r(?!\n)(?!$)/', $head)) {
            throw new HttpError(400, 'A line ended with a bare line feed or carriage return.');
        }
    }

    private function readRequestLine(string $line): void
    {
        if (strlen($line) > $this->limits->request_line) {
            throw new HttpError(414, 'The request line is too long.');
        }

        $parts = explode(' ', $line);

        if (count($parts) !== 3) {
            throw new HttpError(400, 'The request line is not "method target version".');
        }

        [$method, $target, $version] = $parts;

        if (! preg_match(self::TOKEN, $method)) {
            throw new HttpError(400, 'The method is not a token.');
        }

        if ($target === '' || preg_match('/[\x00-\x20\x7f]/', $target)) {
            throw new HttpError(400, 'The request target is empty or holds whitespace or control characters.');
        }

        if (! preg_match('#^HTTP/(\d)\.(\d)$#', $version, $number)) {
            throw new HttpError(400, 'The protocol version is malformed.');
        }

        if ($number[1] !== '1' || ! in_array($number[2], ['0', '1'], true)) {
            throw new HttpError(505, 'Only HTTP/1.0 and HTTP/1.1 are served.');
        }

        [$this->method, $this->target, $this->version] = [$method, $target, "1.{$number[2]}"];
    }

    /** @param list<string> $lines */
    private function readHeaders(array $lines): void
    {
        if (count($lines) > $this->limits->headers) {
            throw new HttpError(431, 'The request has too many header fields.');
        }

        $this->headers = $this->fields($lines);

        if ($this->version === '1.1' && count($this->headers['host'] ?? []) !== 1) {
            throw new HttpError(400, 'An HTTP/1.1 request needs exactly one Host header.');
        }
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, list<string>>
     */
    private function fields(array $lines): array
    {
        $fields = [];

        foreach ($lines as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                throw new HttpError(400, 'Folded header lines are not accepted.');
            }

            $colon = strpos($line, ':');

            if ($colon === false || $colon === 0) {
                throw new HttpError(400, 'A header line has no name.');
            }

            $name = substr($line, 0, $colon);

            if (! preg_match(self::TOKEN, $name)) {
                throw new HttpError(400, 'A header name is not a token, or has whitespace before its colon.');
            }

            $value = trim(substr($line, $colon + 1), " \t");

            if (preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $value)) {
                throw new HttpError(400, 'A header value holds a control character.');
            }

            $fields[strtolower($name)][] = $value;
        }

        return $fields;
    }

    private function frameBody(): void
    {
        $this->body = fopen('php://temp/maxmemory:2097152', 'w+b');
        $this->received = 0;

        $encoding = $this->headers['transfer-encoding'] ?? null;
        $lengths = $this->headers['content-length'] ?? null;

        if (! is_null($encoding)) {
            if (! is_null($lengths)) {
                throw new HttpError(400, 'A request may not carry both Content-Length and Transfer-Encoding.');
            }

            if ($this->version === '1.0') {
                throw new HttpError(400, 'An HTTP/1.0 request may not use Transfer-Encoding.');
            }

            $codings = array_map(fn ($coding) => strtolower(trim($coding)), explode(',', implode(',', $encoding)));

            if ($codings !== ['chunked']) {
                throw new HttpError(501, 'Only the chunked transfer coding is served.');
            }

            $this->expectBody();
            $this->state = State::ChunkSize;

            return;
        }

        if (! is_null($lengths)) {
            $values = array_unique(array_map('trim', explode(',', implode(',', $lengths))));

            if (count($values) !== 1 || ! preg_match('/^\d{1,18}$/', $values[0])) {
                throw new HttpError(400, 'Content-Length is not one non-negative number.');
            }

            $this->remaining = (int) $values[0];

            if ($this->remaining > $this->limits->body) {
                throw new HttpError(413, 'The request body is too large.');
            }

            if ($this->remaining > 0) {
                $this->expectBody();
                $this->state = State::Body;

                return;
            }
        }

        $this->state = State::Done;
    }

    /** A body is coming: answer an Expect header the client sent before it. */
    private function expectBody(): void
    {
        $expect = $this->headers['expect'] ?? null;

        if (is_null($expect)) {
            return;
        }

        if (strtolower(implode(',', $expect)) !== '100-continue') {
            throw new HttpError(417, 'Only "Expect: 100-continue" is understood.');
        }

        $this->continue = $this->version === '1.1';
    }

    private function readBody(): bool
    {
        if ($this->buffer === '') {
            return false;
        }

        $take = substr($this->buffer, 0, $this->remaining);
        $this->buffer = substr($this->buffer, strlen($take));
        $this->write($take);
        $this->remaining -= strlen($take);

        if ($this->remaining === 0) {
            $this->state = State::Done;
        }

        return true;
    }

    private function readChunkSize(): bool
    {
        $end = strpos($this->buffer, "\r\n");

        if ($end === false) {
            if (strlen($this->buffer) > self::CHUNK_LINE) {
                throw new HttpError(400, 'A chunk size line is too long.');
            }

            return false;
        }

        $line = substr($this->buffer, 0, $end);
        $this->buffer = substr($this->buffer, $end + 2);

        // Extensions after ';' are allowed and ignored.
        $size = trim(explode(';', $line, 2)[0], " \t");

        if (! preg_match('/^[0-9A-Fa-f]{1,15}$/', $size)) {
            throw new HttpError(400, 'A chunk size is not a hexadecimal number.');
        }

        $this->remaining = (int) hexdec($size);

        if ($this->received + $this->remaining > $this->limits->body) {
            throw new HttpError(413, 'The request body is too large.');
        }

        $this->state = $this->remaining === 0 ? State::Trailers : State::ChunkData;

        return true;
    }

    private function readChunkData(): bool
    {
        if ($this->buffer === '') {
            return false;
        }

        $take = substr($this->buffer, 0, $this->remaining);
        $this->buffer = substr($this->buffer, strlen($take));
        $this->write($take);
        $this->remaining -= strlen($take);

        if ($this->remaining === 0) {
            $this->state = State::ChunkEnd;
        }

        return true;
    }

    private function readChunkEnd(): bool
    {
        if (strlen($this->buffer) < 2) {
            return false;
        }

        if (! str_starts_with($this->buffer, "\r\n")) {
            throw new HttpError(400, 'A chunk does not end where its size says.');
        }

        $this->buffer = substr($this->buffer, 2);
        $this->state = State::ChunkSize;

        return true;
    }

    /** Trailer fields after the last chunk are read, checked and dropped. */
    private function readTrailers(): bool
    {
        if (str_starts_with($this->buffer, "\r\n")) {
            $this->buffer = substr($this->buffer, 2);
            $this->state = State::Done;

            return true;
        }

        $end = strpos($this->buffer, "\r\n\r\n");

        if ($end === false) {
            if (strlen($this->buffer) > $this->limits->header_bytes) {
                throw new HttpError(431, 'The request trailers are too large.');
            }

            return false;
        }

        if ($end > $this->limits->header_bytes) {
            throw new HttpError(431, 'The request trailers are too large.');
        }

        $this->fields(explode("\r\n", substr($this->buffer, 0, $end)));
        $this->buffer = substr($this->buffer, $end + 4);
        $this->state = State::Done;

        return true;
    }

    private function write(string $bytes): void
    {
        fwrite($this->body, $bytes);
        $this->received += strlen($bytes);
    }

    private function finish(): ParsedRequest
    {
        rewind($this->body);

        $request = new ParsedRequest($this->method, $this->target, $this->version, $this->headers, $this->body);

        [$this->state, $this->method, $this->target, $this->version] = [State::Head, null, null, null];
        [$this->headers, $this->body, $this->remaining, $this->received] = [[], null, 0, 0];

        return $request;
    }
}
