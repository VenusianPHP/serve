<?php

use Mariner\Core\Server\Http1\HttpError;
use Mariner\Core\Server\Http1\Multipart;

afterEach(function () {
    foreach (isset($this->multipart) ? $this->multipart->temporaryFiles() : [] as $path) {
        @unlink($path);
    }
});

/** A stream holding $bytes, as the parser hands a body over. */
function bodyOf(string $bytes)
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);

    return $stream;
}

/** A multipart body from parts of [headers, content], with boundary "XyZ". */
function multipartBody(array $parts, string $preamble = '', string $epilogue = ''): string
{
    $body = $preamble;

    foreach ($parts as [$headers, $content]) {
        $body .= "--XyZ\r\n".implode("\r\n", $headers)."\r\n\r\n".$content."\r\n";
    }

    return $body."--XyZ--".$epilogue;
}

it('splits fields and files, writing each file to a temporary file', function () {
    $this->multipart = new Multipart(bodyOf(multipartBody([
        [['Content-Disposition: form-data; name="title"'], 'Hello'],
        [['Content-Disposition: form-data; name="photo"; filename="cat.png"', 'Content-Type: image/png'], "\x89PNG\r\n\x00binary"],
        [['Content-Disposition: form-data; name="note"'], "two\r\nlines"],
    ], 'preamble', "\r\nepilogue")), 'XyZ');

    [[$name, $file]] = $this->multipart->files();

    expect($this->multipart->fields())->toBe([['title', 'Hello'], ['note', "two\r\nlines"]])
        ->and($name)->toBe('photo')
        ->and([$file['name'], $file['type'], $file['error'], $file['size']])->toBe(['cat.png', 'image/png', UPLOAD_ERR_OK, 13])
        ->and(file_get_contents($file['tmp_name']))->toBe("\x89PNG\r\n\x00binary")
        ->and($this->multipart->temporaryFiles())->toBe([$file['tmp_name']]);
});

it('keeps field names as sent, for nesting later', function () {
    $this->multipart = new Multipart(bodyOf(multipartBody([
        [['Content-Disposition: form-data; name="tags[]"'], 'a'],
        [['Content-Disposition: form-data; name="tags[]"'], 'b'],
        [['Content-Disposition: form-data; name="user[name]"'], 'Ada'],
    ])), 'XyZ');

    expect($this->multipart->fields())->toBe([['tags[]', 'a'], ['tags[]', 'b'], ['user[name]', 'Ada']]);
});

it('reports an empty file input as no file, and strips any path from a filename', function () {
    $this->multipart = new Multipart(bodyOf(multipartBody([
        [['Content-Disposition: form-data; name="empty"; filename=""'], ''],
        [['Content-Disposition: form-data; name="pathed"; filename="C:\\\\Users\\\\me\\\\secret.txt"'], 'x'],
    ])), 'XyZ');

    [[, $empty], [, $pathed]] = $this->multipart->files();

    expect([$empty['error'], $empty['size']])->toBe([UPLOAD_ERR_NO_FILE, 0])
        ->and($pathed['name'])->toBe('secret.txt');
});

it('streams a large file across reads, its bytes intact, boundary look-alikes inside it included', function () {
    // The boundary itself never appears after a line break inside content (RFC 2046); text
    // that only resembles it does, and stays content.
    $content = str_repeat("abc--XyZ\r\n-XyZ\r\n--Xy\r\n", 20_000);
    $this->multipart = new Multipart(bodyOf(multipartBody([
        [['Content-Disposition: form-data; name="big"; filename="big.bin"'], $content],
    ])), 'XyZ');

    [[, $file]] = $this->multipart->files();

    expect($file['size'])->toBe(strlen($content))
        ->and(md5_file($file['tmp_name']))->toBe(md5($content));
});

it('unescapes quoted parameters, and only the escaped character', function () {
    expect(Multipart::parameters('form-data; name="a\\"b"; filename="c\\\\n.txt"; plain=x'))
        ->toBe(['' => 'form-data', 'name' => 'a"b', 'filename' => 'c\\n.txt', 'plain' => 'x']);
});

it('refuses too many parts and too many files with 413', function (array $parts, int $max_parts, int $max_files) {
    try {
        $this->multipart = new Multipart(bodyOf(multipartBody($parts)), 'XyZ', $max_parts, $max_files);
        $this->fail('accepted');
    } catch (HttpError $e) {
        expect($e->status)->toBe(413);
    }
})->with([
    'parts' => [array_fill(0, 3, [['Content-Disposition: form-data; name="f"'], 'v']), 2, 20],
    'files' => [array_fill(0, 3, [['Content-Disposition: form-data; name="f"; filename="a"'], 'v']), 1000, 2],
]);

it('refuses the boundary inside a part\'s content', function () {
    $body = multipartBody([[['Content-Disposition: form-data; name="a"'], "before\r\n--XyZ-inside"]]);

    expect(fn () => new Multipart(bodyOf($body), 'XyZ'))->toThrow(HttpError::class, 'not followed by a line break');
});

it('refuses a body that is not the multipart it claims to be', function (string $body, string $boundary) {
    expect(fn () => new Multipart(bodyOf($body), $boundary))->toThrow(HttpError::class);
})->with([
    'no opening boundary' => ['just text', 'XyZ'],
    'no closing boundary' => ["--XyZ\r\nContent-Disposition: form-data; name=\"a\"\r\n\r\nvalue", 'XyZ'],
    'a part without a name' => [multipartBody([[['Content-Disposition: form-data'], 'v']]), 'XyZ'],
    'a part that is not form-data' => [multipartBody([[['Content-Disposition: attachment; name="a"'], 'v']]), 'XyZ'],
    'a malformed part header' => [multipartBody([[['nonsense'], 'v']]), 'XyZ'],
    'an empty boundary' => [multipartBody([]), ''],
    'a boundary over 70 characters' => [multipartBody([]), str_repeat('b', 71)],
]);
