<?php

namespace Mariner\Core\Server;

use ReflectionProperty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A response as bytes for the wire: the status line and headers, then the body, a file's part
 * read from disk as the connection writes it rather than loaded whole.
 */
final class ResponseWriter
{
    /**
     * The head, and the body as a string or a file to stream.
     *
     * @return array{0: string, 1: string|FileBody}
     */
    public static function write(Response $response, string $version, bool $keep_alive, bool $head_request): array
    {
        $status = $response->getStatusCode();
        $no_body = $status < 200 || $status === 204 || $status === 304;

        if ($response instanceof BinaryFileResponse) {
            $body = $head_request || $no_body ? '' : self::fileBody($response);
            $length = $no_body ? null : (int) $response->headers->get('Content-Length', $body instanceof FileBody ? (string) $body->length : '0');
        } else {
            $content = (string) $response->getContent();
            $body = $head_request || $no_body ? '' : $content;
            // HEAD: the length only if the app set one; the router's prepare() emptied the body.
            $length = match (true) {
                $no_body => null,
                $head_request => $response->headers->has('Content-Length') ? (int) $response->headers->get('Content-Length') : null,
                default => strlen($content),
            };
        }

        return [self::head($response, $version, $keep_alive, $length), $body];
    }

    /**
     * The status line and headers, with Content-Length $length, or Transfer-Encoding: chunked when
     * $length is null and $chunked, or neither (a body that ends with the connection, or none).
     */
    public static function head(Response $response, string $version, bool $keep_alive, ?int $length, bool $chunked = false): string
    {
        $status = $response->getStatusCode();
        $headers = clone $response->headers;

        $headers->remove('Content-Length');
        $headers->remove('Transfer-Encoding');

        if (! is_null($length) && $status >= 200 && $status !== 204) {
            $headers->set('Content-Length', (string) $length);
        } elseif ($chunked) {
            $headers->set('Transfer-Encoding', 'chunked');
        }

        $headers->set('Connection', $keep_alive ? 'keep-alive' : 'close');

        $lines = [sprintf('HTTP/%s %d %s', $version, $status, Response::$statusTexts[$status] ?? '')];

        foreach ($headers->allPreserveCaseWithoutCookies() as $name => $values) {
            foreach ($values as $value) {
                $lines[] = "{$name}: {$value}";
            }
        }

        foreach ($headers->getCookies() as $cookie) {
            $lines[] = 'Set-Cookie: '.$cookie;
        }

        return implode("\r\n", $lines)."\r\n\r\n";
    }

    /** The part of the file the response sends: all of it, or the range prepare() settled on. */
    private static function fileBody(BinaryFileResponse $response): FileBody
    {
        $file = $response->getFile();
        $offset = 0;
        $length = $file->getSize();

        if (preg_match('/^bytes (\d+)-(\d+)\//', (string) $response->headers->get('Content-Range'), $range)) {
            $offset = (int) $range[1];
            $length = (int) $range[2] - $offset + 1;
        }

        // Symfony keeps "delete after send" to itself; the connection deletes the file once written.
        $delete = (new ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend'))->getValue($response);

        return new FileBody($file->getPathname(), $offset, $length, (bool) $delete);
    }
}
