<?php

namespace Mariner\Core\Server\Http1;

/**
 * A multipart/form-data body split into its fields and its files, as PHP's own SAPI would fill
 * $_POST and $_FILES. File parts are written to temporary files as they are read, never held
 * whole in memory; the caller deletes them once the request is done (see files()).
 */
final class Multipart
{
    private const int READ = 65_536;

    private const int PART_HEAD = 8_192;

    /** @var list<array{0: string, 1: string}> field name => value, in order, names as sent */
    private array $fields = [];

    /** @var list<array{0: string, 1: array{name: string, type: string, tmp_name: string, error: int, size: int}}> */
    private array $files = [];

    /** @var list<string> every temporary file written, to delete when the request ends */
    private array $temporary = [];

    /**
     * @param  resource  $body
     *
     * @throws HttpError  400 for a body that is not the multipart it claims to be, 413 for too many parts
     */
    public function __construct(mixed $body, string $boundary, private readonly int $max_parts = 1_000, private readonly int $max_files = 20)
    {
        if ($boundary === '' || strlen($boundary) > 70) {
            throw new HttpError(400, 'The multipart boundary is missing or longer than 70 characters.');
        }

        $this->read($body, "--{$boundary}");
    }

    /** @return list<array{0: string, 1: string}> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<array{0: string, 1: array{name: string, type: string, tmp_name: string, error: int, size: int}}> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return list<string> */
    public function temporaryFiles(): array
    {
        return $this->temporary;
    }

    /** @param resource $body */
    private function read(mixed $body, string $delimiter): void
    {
        rewind($body);
        $buffer = '';

        // Up to the first delimiter is preamble, ignored.
        while (($at = strpos($buffer, $delimiter)) === false) {
            if (feof($body)) {
                throw new HttpError(400, 'The multipart body has no opening boundary.');
            }

            $buffer = substr($buffer, -strlen($delimiter)).fread($body, self::READ);
        }

        $buffer = substr($buffer, $at + strlen($delimiter));
        $parts = 0;

        while (true) {
            $buffer = $this->fill($body, $buffer, 2);

            if (str_starts_with($buffer, '--')) {
                return;  // the closing delimiter; what follows is epilogue, ignored
            }

            if (! str_starts_with($buffer, "\r\n")) {
                throw new HttpError(400, 'A multipart boundary is not followed by a line break.');
            }

            if (++$parts > $this->max_parts) {
                throw new HttpError(413, 'The multipart body has too many parts.');
            }

            $buffer = substr($buffer, 2);
            [$headers, $buffer] = $this->partHead($body, $buffer);
            $buffer = $this->partBody($body, $buffer, "\r\n{$delimiter}", $headers);
        }
    }

    /**
     * @param  resource  $body
     * @return array{0: array<string, string>, 1: string}
     */
    private function partHead(mixed $body, string $buffer): array
    {
        while (($end = strpos($buffer, "\r\n\r\n")) === false) {
            if (strlen($buffer) > self::PART_HEAD) {
                throw new HttpError(400, 'A multipart part has headers that are too large.');
            }

            if (feof($body)) {
                throw new HttpError(400, 'The multipart body ends inside a part\'s headers.');
            }

            $buffer .= fread($body, self::READ);
        }

        $headers = [];

        foreach (explode("\r\n", substr($buffer, 0, $end)) as $line) {
            if (! str_contains($line, ':')) {
                throw new HttpError(400, 'A multipart part has a malformed header.');
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return [$headers, substr($buffer, $end + 4)];
    }

    /**
     * Read a part's content up to the next delimiter: into a field, or straight into a file.
     *
     * @param  resource  $body
     * @param  array<string, string>  $headers
     */
    private function partBody(mixed $body, string $buffer, string $delimiter, array $headers): string
    {
        $disposition = self::parameters($headers['content-disposition'] ?? '');

        if (($disposition[''] ?? '') !== 'form-data' || ! isset($disposition['name'])) {
            throw new HttpError(400, 'A multipart part is not form-data with a name.');
        }

        $is_file = array_key_exists('filename', $disposition);

        if ($is_file && count($this->files) >= $this->max_files) {
            throw new HttpError(413, 'The request has too many files.');
        }

        $out = $is_file ? fopen($path = tempnam(sys_get_temp_dir(), 'serve-upload-'), 'wb') : null;

        if ($is_file) {
            $this->temporary[] = $path;
        }

        $value = '';
        $size = 0;

        while (($at = strpos($buffer, $delimiter)) === false) {
            if (feof($body)) {
                throw new HttpError(400, 'The multipart body ends inside a part.');
            }

            // Keep back what could be the start of the delimiter; write or keep the rest.
            $safe = max(0, strlen($buffer) - strlen($delimiter));
            $chunk = substr($buffer, 0, $safe);
            $buffer = substr($buffer, $safe).fread($body, self::READ);

            $size += strlen($chunk);
            $is_file ? fwrite($out, $chunk) : $value .= $chunk;
        }

        $chunk = substr($buffer, 0, $at);
        $size += strlen($chunk);
        $is_file ? fwrite($out, $chunk) : $value .= $chunk;

        if ($is_file) {
            fclose($out);
            $this->files[] = [$disposition['name'], [
                'name' => basename(str_replace('\\', '/', $disposition['filename'])),
                'type' => $headers['content-type'] ?? 'application/octet-stream',
                'tmp_name' => $path,
                // An empty filename is a file input left empty, as PHP reports it.
                'error' => $disposition['filename'] === '' ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK,
                'size' => $size,
            ]];
        } else {
            $this->fields[] = [$disposition['name'], $value];
        }

        return substr($buffer, $at + strlen($delimiter));
    }

    /**
     * @param  resource  $body
     */
    private function fill(mixed $body, string $buffer, int $bytes): string
    {
        while (strlen($buffer) < $bytes && ! feof($body)) {
            $buffer .= fread($body, self::READ);
        }

        if (strlen($buffer) < $bytes) {
            throw new HttpError(400, 'The multipart body has no closing boundary.');
        }

        return $buffer;
    }

    /**
     * A header value's parameters: '' => the value before the first ';', then name => value.
     *
     * @return array<string, string>
     */
    public static function parameters(string $value): array
    {
        $parameters = [];

        preg_match_all('/;\s*([^=;\s]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', $value, $matches, PREG_SET_ORDER);

        $parameters[''] = strtolower(trim(explode(';', $value, 2)[0]));

        foreach ($matches as [, $name, $raw]) {
            $raw = trim($raw);
            $parameters[strtolower($name)] = str_starts_with($raw, '"')
                ? preg_replace('/\\\\(.)/s', '$1', substr($raw, 1, -1))
                : $raw;
        }

        return $parameters;
    }
}
