<?php

namespace Mariner\Core\Server\Http1;

/** One request off the wire: its line, its headers, and its body, decoded from any chunking. */
final class ParsedRequest
{
    /**
     * @param  array<string, list<string>>  $headers  lower-cased name => values, in arrival order
     * @param  resource  $body  read from the start; in memory up to 2 MB, on disk beyond
     */
    public function __construct(
        public readonly string $method,
        public readonly string $target,
        public readonly string $version,
        public readonly array $headers,
        public readonly mixed $body,
    ) {}

    /** The header's values joined as one field value, or null when it was not sent. */
    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return is_null($values) ? null : implode(', ', $values);
    }

    /** Whether the connection stays open for another request after this one is answered. */
    public function keepAlive(): bool
    {
        $tokens = array_map('trim', explode(',', strtolower($this->header('connection') ?? '')));

        return $this->version === '1.1'
            ? ! in_array('close', $tokens, true)
            : in_array('keep-alive', $tokens, true);
    }

    /** The whole body as a string. */
    public function content(): string
    {
        rewind($this->body);

        return (string) stream_get_contents($this->body);
    }
}
