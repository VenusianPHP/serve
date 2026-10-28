<?php

namespace Mariner\Core\Server;

/** Part of a file to write as a response body, read as the connection can take it. */
final class FileBody
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(
        public readonly string $path,
        private int $offset,
        public readonly int $length,
        public readonly bool $delete_after,
    ) {
        $this->remaining = $length;
    }

    private int $remaining;

    /** Up to $bytes more of the file; '' once it is all read. */
    public function read(int $bytes): string
    {
        if ($this->remaining <= 0) {
            return '';
        }

        // fopen() reads phar:// paths too: a packaged app's public files stream from the phar.
        $this->stream ??= fopen($this->path, 'rb');

        if ($this->offset > 0) {
            fseek($this->stream, $this->offset);
            $this->offset = 0;
        }

        $chunk = (string) fread($this->stream, min($bytes, $this->remaining));
        $this->remaining -= strlen($chunk);

        if ($chunk === '') {
            $this->remaining = 0;  // the file is shorter than promised: end the body here
        }

        return $chunk;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;

        if ($this->delete_after && is_file($this->path)) {
            unlink($this->path);
        }
    }
}
