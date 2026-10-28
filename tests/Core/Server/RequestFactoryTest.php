<?php

use Mariner\Core\Server\Http1\Parser;
use Mariner\Core\Server\RequestFactory;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(function () {
    foreach ($this->factory?->uploads() ?? [] as $path) {
        @unlink($path);
    }
    WebApp::tearDown($this);
});

/** The Mariner request a factory makes from raw HTTP bytes. */
function requestFrom($test, string $bytes, bool $https = false): Request
{
    $parser = new Parser;
    $parser->push($bytes);
    $test->factory = new RequestFactory('serve.test', 8080, $https);

    return $test->factory->make($parser->next(), '203.0.113.9:51234');
}

it('fills the server variables, query and headers', function () {
    $request = requestFrom($this, "GET /users/7?sort=name&tags[]=a&tags[]=b HTTP/1.1\r\nHost: example.test:8080\r\nAccept: application/json\r\nX-Trace: abc\r\n\r\n");

    expect([$request->method(), $request->path(), $request->getRequestUri()])->toBe(['GET', 'users/7', '/users/7?sort=name&tags[]=a&tags[]=b'])
        ->and($request->query())->toBe(['sort' => 'name', 'tags' => ['a', 'b']])
        ->and($request->header('x-trace'))->toBe('abc')
        ->and($request->expectsJson())->toBeTrue()
        ->and($request->getHost())->toBe('example.test')
        ->and($request->getPort())->toBe(8080)
        ->and($request->ip())->toBe('203.0.113.9')
        ->and($request->server('REMOTE_PORT'))->toBe(51234)
        ->and($request->getProtocolVersion())->toBe('HTTP/1.1')
        ->and($request->isSecure())->toBeFalse();
});

it('reads cookies, the first of a repeated name winning', function () {
    $request = requestFrom($this, "GET / HTTP/1.1\r\nHost: h\r\nCookie: a=1; b=two%20words\r\nCookie: a=ignored; c=3\r\n\r\n");

    expect($request->cookies->all())->toBe(['a' => '1', 'b' => 'two words', 'c' => '3']);
});

it('reads an urlencoded form, nested as PHP nests it', function () {
    $body = 'name=Ada&roles[]=admin&roles[]=dev&profile[city]=London';
    $request = requestFrom($this, "POST /form HTTP/1.1\r\nHost: h\r\nContent-Type: application/x-www-form-urlencoded; charset=UTF-8\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);

    expect($request->post())->toBe(['name' => 'Ada', 'roles' => ['admin', 'dev'], 'profile' => ['city' => 'London']])
        ->and($request->input('profile.city'))->toBe('London');
});

it('keeps any other body as content', function () {
    $body = '{"name":"Ada"}';
    $request = requestFrom($this, "POST /api HTTP/1.1\r\nHost: h\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);

    expect($request->getContent())->toBe($body)
        ->and($request->json('name'))->toBe('Ada')
        ->and($request->post())->toBe([]);
});

it('reads a multipart form: fields, and files at the places their names nest to', function () {
    $body = "--B\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nHi\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"me.png\"\r\nContent-Type: image/png\r\n\r\nPNG\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"docs[]\"; filename=\"a.txt\"\r\n\r\nA\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"docs[]\"; filename=\"b.txt\"\r\n\r\nB\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"skipped\"; filename=\"\"\r\n\r\n\r\n"
        ."--B--";
    $request = requestFrom($this, "POST /upload HTTP/1.1\r\nHost: h\r\nContent-Type: multipart/form-data; boundary=B\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);

    $avatar = $request->file('avatar');
    $docs = $request->file('docs');

    expect($request->input('title'))->toBe('Hi')
        ->and($avatar->getClientOriginalName())->toBe('me.png')
        ->and($avatar->getClientMimeType())->toBe('image/png')
        ->and($avatar->isValid())->toBeTrue()
        ->and($avatar->get())->toBe('PNG')
        ->and(array_map(fn ($file) => $file->get(), $docs))->toBe(['A', 'B'])
        ->and($request->file('skipped'))->toBeNull()
        ->and($request->hasFile('skipped'))->toBeFalse()
        ->and($this->factory->uploads())->toHaveCount(4);
});

it('marks the request secure when the server serves behind TLS', function () {
    expect(requestFrom($this, "GET / HTTP/1.1\r\nHost: h\r\n\r\n", https: true)->isSecure())->toBeTrue();
});

it('never takes a path from the client: a hostile filename still lands in a server temp file, and move() touches only that', function () {
    $passwd = is_file('/etc/passwd') ? md5_file('/etc/passwd') : null;
    $body = "--B\r\nContent-Disposition: form-data; name=\"doc\"; filename=\"../../etc/passwd\"\r\n\r\nnot the password file\r\n"
        ."--B\r\nContent-Disposition: form-data; name=\"other\"; filename=\"/etc/passwd\"\r\n\r\nnor this\r\n--B--";
    $request = requestFrom($this, "POST /upload HTTP/1.1\r\nHost: h\r\nContent-Type: multipart/form-data; boundary=B\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body);

    $doc = $request->file('doc');
    $temporary = realpath(sys_get_temp_dir());

    expect($doc->getClientOriginalName())->toBe('passwd')
        ->and($request->file('other')->getClientOriginalName())->toBe('passwd')
        ->and(dirname(realpath($doc->getPathname())))->toBe($temporary)
        ->and(basename($doc->getPathname()))->toStartWith('serve-upload-')
        ->and($doc->get())->toBe('not the password file');

    $target = sys_get_temp_dir().'/serve-move-'.bin2hex(random_bytes(4));
    $source = $doc->getPathname();
    $moved = $doc->move($target);

    expect(dirname(realpath($moved->getPathname())))->toBe(realpath($target))
        ->and(file_get_contents($moved->getPathname()))->toBe('not the password file')
        ->and(file_exists($source))->toBeFalse()
        ->and(is_file('/etc/passwd') ? md5_file('/etc/passwd') : null)->toBe($passwd);

    unlink($moved->getPathname());
    rmdir($target);
});
