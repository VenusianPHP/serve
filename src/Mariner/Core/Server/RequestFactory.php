<?php

namespace Mariner\Core\Server;

use Mariner\Core\Server\Http1\HttpError;
use Mariner\Core\Server\Http1\Multipart;
use Mariner\Core\Server\Http1\ParsedRequest;
use Mariner\Http\Request;
use Voyager\Filesystem\UploadedFile;

/**
 * A request off the wire as the Mariner Request PHP's own SAPI would have built: the server
 * variables, the query, cookies, form fields and uploaded files. Form fields nest exactly as
 * PHP nests them (tags[], user[name]), because PHP's own parser builds them.
 */
final class RequestFactory
{
    /** @var list<string> the temporary upload files of the last request made */
    private array $uploads = [];

    public function __construct(
        private readonly string $server_name,
        private readonly int $server_port,
        private readonly bool $https = false,
    ) {}

    /**
     * @param  string  $remote  the client's "address:port"
     *
     * @throws HttpError  a body that is not the form it says it is
     */
    public function make(ParsedRequest $parsed, string $remote): Request
    {
        $this->uploads = [];

        $server = $this->server($parsed, $remote);
        parse_str($server['QUERY_STRING'], $query);

        $post = [];
        $files = [];
        $content = null;

        [$type, $parameters] = $this->contentType($parsed);

        if ($type === 'application/x-www-form-urlencoded') {
            parse_str($parsed->content(), $post);
        } elseif ($type === 'multipart/form-data') {
            $multipart = new Multipart($parsed->body, $parameters['boundary'] ?? '', max_files: (int) ini_get('max_file_uploads') ?: 20);
            $this->uploads = $multipart->temporaryFiles();
            $post = $this->nest($multipart->fields());
            $files = $this->nestFiles($multipart->files());
        } else {
            $content = $parsed->body;
        }

        return new Request($query, $post, [], $this->cookies($parsed), $files, $server, $content);
    }

    /**
     * The temporary files the last request's uploads were written to: delete them once it is done.
     *
     * @return list<string>
     */
    public function uploads(): array
    {
        return $this->uploads;
    }

    /** @return array<string, mixed> */
    private function server(ParsedRequest $parsed, string $remote): array
    {
        [$path, $query] = array_pad(explode('?', $parsed->target, 2), 2, '');
        $colon = strrpos($remote, ':');

        $server = [
            'SERVER_SOFTWARE' => 'Venusian',
            'SERVER_NAME' => $this->server_name,
            'SERVER_PORT' => $this->server_port,
            'SERVER_PROTOCOL' => "HTTP/{$parsed->version}",
            'REQUEST_METHOD' => $parsed->method,
            'REQUEST_URI' => $parsed->target,
            'QUERY_STRING' => $query,
            'PATH_INFO' => $path,
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'REMOTE_ADDR' => $colon === false ? $remote : trim(substr($remote, 0, $colon), '[]'),
            'REMOTE_PORT' => $colon === false ? 0 : (int) substr($remote, $colon + 1),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ];

        if ($this->https) {
            $server['HTTPS'] = 'on';
        }

        foreach ($parsed->headers as $name => $values) {
            $key = strtoupper(str_replace('-', '_', $name));
            $value = $name === 'cookie' ? implode('; ', $values) : implode(', ', $values);

            match ($key) {
                'CONTENT_TYPE', 'CONTENT_LENGTH' => $server[$key] = $value,
                default => $server["HTTP_{$key}"] = $value,
            };
        }

        return $server;
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function contentType(ParsedRequest $parsed): array
    {
        $parameters = Multipart::parameters($parsed->header('content-type') ?? '');

        return [$parameters[''], $parameters];
    }

    /** @return array<string, string> */
    private function cookies(ParsedRequest $parsed): array
    {
        $cookies = [];

        foreach (explode(';', implode(';', $parsed->headers['cookie'] ?? [])) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);
            $name = trim($name);

            // The first of two cookies with one name wins, as PHP's SAPI has it.
            if ($name !== '' && ! array_key_exists($name, $cookies)) {
                $cookies[$name] = urldecode(trim($value));
            }
        }

        return $cookies;
    }

    /**
     * Fields as PHP nests them: through PHP's own parser, max_input_vars and all.
     *
     * @param  list<array{0: string, 1: string}>  $fields
     * @return array<string, mixed>
     */
    private function nest(array $fields): array
    {
        $pairs = array_map(fn ($field) => rawurlencode($field[0]).'='.rawurlencode($field[1]), $fields);
        parse_str(implode('&', $pairs), $nested);

        return $nested;
    }

    /**
     * Uploaded files at the places their field names nest to, as uploads PHP didn't receive
     * itself: is_uploaded_file() can't vouch for them, so they are made in test mode, and as the
     * framework's own UploadedFile, which the request hands on as it is (converting a Symfony one
     * would drop test mode, and with it isValid()).
     *
     * @param  list<array{0: string, 1: array{name: string, type: string, tmp_name: string, error: int, size: int}}>  $files
     * @return array<string, mixed>
     */
    private function nestFiles(array $files): array
    {
        $placed = [];

        foreach ($files as $index => [$name, $file]) {
            $placed[] = [$name, "\0{$index}"];
        }

        $nested = $this->nest($placed);

        array_walk_recursive($nested, function (&$leaf) use ($files) {
            if (is_string($leaf) && str_starts_with($leaf, "\0")) {
                $file = $files[(int) substr($leaf, 1)][1];
                // Test mode skips is_uploaded_file(). That is safe only because tmp_name is always a
                // temporary file Multipart itself created (tempnam() in the system temp directory),
                // never anything the client sent: the client's filename is kept as the original
                // name, path stripped, and never used as a path to read, move or delete.
                // An empty file input stays PHP's own entry: the FileBag reads it as no file.
                $leaf = $file['error'] === UPLOAD_ERR_NO_FILE
                    ? $file
                    : new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['error'], true);
            }
        });

        return $nested;
    }
}
