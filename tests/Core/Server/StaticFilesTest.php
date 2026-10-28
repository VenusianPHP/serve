<?php

use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = WebApp::copy();
    @mkdir($this->root.'/public/css', 0777, true);
    file_put_contents($this->root.'/public/css/app.css', 'body { color: red; }');
    file_put_contents($this->root.'/public/secret.php', '<?php echo "source";');
    file_put_contents($this->root.'/outside.txt', 'not public');
    symlink($this->root.'/outside.txt', $this->root.'/public/escape.txt');

    $this->app = WebApp::bootFrom($this->root);
    config(['session.driver' => 'array']);
    $this->loop = $this->app['event-loop'];
});
afterEach(function () {
    $this->server?->stop();
    WebApp::tearDown($this);
    (new Filesystem)->deleteDirectory($this->root);
});

/** The raw answer to $raw, from a server with $options on the test's loop. */
function fetchStatic($test, string $raw, array $options = []): string
{
    $test->server ??= (new HttpServer($test->app, new ServerOptions(...[...$options, 'port' => 0])))->attach($test->loop);
    $socket = stream_socket_client("tcp://127.0.0.1:{$test->server->port()}");
    fwrite($socket, $raw);
    stream_set_blocking($socket, false);
    $received = '';
    $pace = $test->loop->every(0.001, fn () => null, 'static.pace');

    try {
        $test->loop->until(function () use ($socket, &$received) {
            $received .= (string) fread($socket, 65_536);

            return feof($socket);
        });
    } finally {
        $pace->cancel();
        $test->loop->forget('static.pace');
    }

    return $received;
}

function staticStatus(string $raw): int
{
    return (int) explode(' ', $raw, 3)[1];
}

it('serves a file from public/ without the app, with its type, and a range of it', function () {
    $whole = fetchStatic($this, "GET /css/app.css HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");
    $range = fetchStatic($this, "GET /css/app.css HTTP/1.1\r\nHost: h\r\nRange: bytes=0-3\r\nConnection: close\r\n\r\n");
    $head = fetchStatic($this, "HEAD /css/app.css HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");

    expect(staticStatus($whole))->toBe(200)
        ->and($whole)->toContain('Content-Type: text/css')->toEndWith("\r\n\r\nbody { color: red; }")
        ->and(staticStatus($range))->toBe(206)
        ->and($range)->toEndWith("\r\n\r\nbody")
        ->and(staticStatus($head))->toBe(200)
        ->and($head)->toEndWith("\r\n\r\n");
});

it('never serves outside public/, a .php file, or a directory: the app answers those', function (string $target) {
    $answer = fetchStatic($this, "GET {$target} HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n");

    expect(staticStatus($answer))->toBe(404)
        ->and($answer)->not->toContain('not public')
        ->and($answer)->not->toContain('<?php');
})->with([
    'a parent path' => ['/../outside.txt'],
    'an encoded parent path' => ['/%2e%2e/outside.txt'],
    'a symlink out of public/' => ['/escape.txt'],
    'a .php file' => ['/secret.php'],
    'a directory' => ['/css'],
]);

it('leaves every path to the app when told not to serve files', function () {
    $answer = fetchStatic($this, "GET /css/app.css HTTP/1.1\r\nHost: h\r\nConnection: close\r\n\r\n", ['static' => false]);

    expect(staticStatus($answer))->toBe(404);
});
