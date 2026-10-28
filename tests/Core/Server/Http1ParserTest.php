<?php

use Mariner\Core\Server\Http1\HttpError;
use Mariner\Core\Server\Http1\Limits;
use Mariner\Core\Server\Http1\ParsedRequest;
use Mariner\Core\Server\Http1\Parser;

dataset('feeds', [
    'all at once' => [false],
    'a byte at a time' => [true],
]);

/**
 * Every request in $bytes, fed whole or a byte at a time.
 *
 * @return list<ParsedRequest>
 */
function parseAll(string $bytes, bool $bytewise = false, ?Limits $limits = null): array
{
    $parser = new Parser($limits ?? new Limits);
    $requests = [];

    foreach ($bytewise ? str_split($bytes) : [$bytes] as $piece) {
        $parser->push($piece);

        while ($request = $parser->next()) {
            $requests[] = $request;
        }
    }

    return $requests;
}

/** The status the parser refuses $bytes with, or null when it accepts them. */
function refusal(string $bytes, bool $bytewise = false, ?Limits $limits = null): ?int
{
    try {
        parseAll($bytes, $bytewise, $limits);
    } catch (HttpError $e) {
        return $e->status;
    }

    return null;
}

it('reads a request line, its headers and a sized body', function (bool $bytewise) {
    [$request] = parseAll("POST /submit?x=1 HTTP/1.1\r\nHost: example.test\r\nContent-Type: text/plain\r\nX-Twice: a\r\nx-twice: b\r\nContent-Length: 5\r\n\r\nhello", $bytewise);

    expect([$request->method, $request->target, $request->version])->toBe(['POST', '/submit?x=1', '1.1'])
        ->and($request->header('host'))->toBe('example.test')
        ->and($request->header('X-TWICE'))->toBe('a, b')
        ->and($request->headers['x-twice'])->toBe(['a', 'b'])
        ->and($request->content())->toBe('hello')
        ->and($request->header('missing'))->toBeNull();
})->with('feeds');

it('trims header values but keeps inner spaces', function () {
    [$request] = parseAll("GET / HTTP/1.1\r\nHost: h\r\nX-Spaced: \t one two \t\r\n\r\n");

    expect($request->header('x-spaced'))->toBe('one two');
});

it('reads pipelined requests one after another', function (bool $bytewise) {
    $requests = parseAll(
        "GET /one HTTP/1.1\r\nHost: h\r\n\r\n".
        "POST /two HTTP/1.1\r\nHost: h\r\nContent-Length: 3\r\n\r\nabc".
        "GET /three HTTP/1.1\r\nHost: h\r\n\r\n",
        $bytewise
    );

    expect(array_map(fn ($r) => $r->target, $requests))->toBe(['/one', '/two', '/three'])
        ->and($requests[1]->content())->toBe('abc')
        ->and($requests[2]->content())->toBe('');
})->with('feeds');

it('waits for the rest of a request, and knows it is mid-request meanwhile', function () {
    $parser = new Parser;

    expect($parser->midRequest())->toBeFalse();

    $parser->push("POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 4\r\n\r\nab");

    expect($parser->next())->toBeNull()
        ->and($parser->midRequest())->toBeTrue();

    $parser->push('cd');

    expect($parser->next()->content())->toBe('abcd')
        ->and($parser->midRequest())->toBeFalse();
});

it('ignores blank lines before a request', function () {
    expect(parseAll("\r\n\r\nGET / HTTP/1.1\r\nHost: h\r\n\r\n"))->toHaveCount(1);
});

it('decodes a chunked body, with extensions and trailers', function (bool $bytewise) {
    [$request, $next] = parseAll(
        "POST /up HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\n".
        "5;name=value\r\nhello\r\n6\r\n world\r\n0\r\nX-Trailer: t\r\n\r\n".
        "GET /after HTTP/1.1\r\nHost: h\r\n\r\n",
        $bytewise
    );

    expect($request->content())->toBe('hello world')
        ->and($request->header('x-trailer'))->toBeNull()
        ->and($next->target)->toBe('/after');
})->with('feeds');

it('reads an HTTP/1.0 request without a Host header', function () {
    [$request] = parseAll("GET /old HTTP/1.0\r\n\r\n");

    expect($request->version)->toBe('1.0');
});

it('keeps HTTP/1.1 connections open and HTTP/1.0 ones closed unless they ask otherwise', function (string $head, bool $keep) {
    [$request] = parseAll($head."\r\n");

    expect($request->keepAlive())->toBe($keep);
})->with([
    '1.1' => ["GET / HTTP/1.1\r\nHost: h\r\n", true],
    '1.1, close' => ["GET / HTTP/1.1\r\nHost: h\r\nConnection: Upgrade, Close\r\n", false],
    '1.0' => ["GET / HTTP/1.0\r\n", false],
    '1.0, keep-alive' => ["GET / HTTP/1.0\r\nConnection: keep-alive\r\n", true],
]);

it('flags a request that expects 100 Continue, once', function () {
    $parser = new Parser;
    $parser->push("PUT /big HTTP/1.1\r\nHost: h\r\nExpect: 100-continue\r\nContent-Length: 2\r\n\r\n");

    expect($parser->next())->toBeNull()
        ->and($parser->takeContinue())->toBeTrue()
        ->and($parser->takeContinue())->toBeFalse();

    $parser->push('ok');

    expect($parser->next()->content())->toBe('ok');
});

it('does not flag 100 Continue for a request without a body', function () {
    $parser = new Parser;
    $parser->push("GET / HTTP/1.1\r\nHost: h\r\nExpect: 100-continue\r\n\r\n");
    $parser->next();

    expect($parser->takeContinue())->toBeFalse();
});

it('refuses what lets two parsers disagree about where a request ends', function (string $bytes, int $status) {
    expect(refusal($bytes))->toBe($status)
        ->and(refusal($bytes, bytewise: true))->toBe($status);
})->with([
    'Content-Length beside Transfer-Encoding' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 3\r\nTransfer-Encoding: chunked\r\n\r\n", 400],
    'Content-Lengths that differ' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 3\r\nContent-Length: 4\r\n\r\nabc", 400],
    'a Content-Length list that differs' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 3, 4\r\n\r\nabc", 400],
    'a signed Content-Length' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: +3\r\n\r\nabc", 400],
    'a folded header' => ["GET / HTTP/1.1\r\nHost: h\r\nX-A: one\r\n two\r\n\r\n", 400],
    'whitespace before a colon' => ["GET / HTTP/1.1\r\nHost: h\r\nX-A : one\r\n\r\n", 400],
    'a bare line feed' => ["GET / HTTP/1.1\nHost: h\n\n", 400],
    'a bare carriage return' => ["GET / HTTP/1.1\r\nHost: h\rX-A: b\r\n\r\n", 400],
    'a control character in a value' => ["GET / HTTP/1.1\r\nHost: h\r\nX-A: a\x01b\r\n\r\n", 400],
    'a coding other than chunked' => ["POST / HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: gzip, chunked\r\n\r\n", 501],
    'Transfer-Encoding on HTTP/1.0' => ["POST / HTTP/1.0\r\nTransfer-Encoding: chunked\r\n\r\n", 400],
    'a bad chunk size' => ["POST / HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\nzz\r\n", 400],
    'a chunk longer than its size' => ["POST / HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nabc\r\n0\r\n\r\n", 400],
]);

it('refuses malformed requests', function (string $bytes, int $status) {
    expect(refusal($bytes))->toBe($status);
})->with([
    'no Host on 1.1' => ["GET / HTTP/1.1\r\n\r\n", 400],
    'two Hosts' => ["GET / HTTP/1.1\r\nHost: a\r\nHost: b\r\n\r\n", 400],
    'a request line of two parts' => ["GET /\r\nHost: h\r\n\r\n", 400],
    'a double space in the request line' => ["GET  / HTTP/1.1\r\nHost: h\r\n\r\n", 400],
    'a method that is not a token' => ["G(T / HTTP/1.1\r\nHost: h\r\n\r\n", 400],
    'a malformed version' => ["GET / HTTP/one\r\nHost: h\r\n\r\n", 400],
    'HTTP/2.0' => ["GET / HTTP/2.0\r\nHost: h\r\n\r\n", 505],
    'a header line without a colon' => ["GET / HTTP/1.1\r\nHost: h\r\nnope\r\n\r\n", 400],
    'an Expect other than 100-continue' => ["POST / HTTP/1.1\r\nHost: h\r\nExpect: something\r\nContent-Length: 1\r\n\r\na", 417],
]);

it('enforces its limits with the status for each', function (string $bytes, Limits $limits, int $status) {
    expect(refusal($bytes, false, $limits))->toBe($status)
        ->and(refusal($bytes, true, $limits))->toBe($status);
})->with([
    'a long request line' => ['GET /'.str_repeat('a', 100)." HTTP/1.1\r\nHost: h\r\n\r\n", new Limits(request_line: 50), 414],
    'large headers' => ["GET / HTTP/1.1\r\nHost: h\r\nX-Big: ".str_repeat('a', 200)."\r\n\r\n", new Limits(header_bytes: 100), 431],
    'too many headers' => ["GET / HTTP/1.1\r\nHost: h\r\nA: 1\r\nB: 2\r\nC: 3\r\n\r\n", new Limits(headers: 3), 431],
    'a large sized body' => ["POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 11\r\n\r\n", new Limits(body: 10), 413],
    'a large chunked body' => ["POST / HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\n6\r\nabcdef\r\n6\r\nabcdef\r\n0\r\n\r\n", new Limits(body: 10), 413],
    'large trailers' => ["POST / HTTP/1.1\r\nHost: h\r\nTransfer-Encoding: chunked\r\n\r\n0\r\nX-T: ".str_repeat('a', 200)."\r\n\r\n", new Limits(header_bytes: 100), 431],
]);

it('refuses a huge body by its declared size, before reading any of it', function () {
    $parser = new Parser(new Limits(body: 10));
    $parser->push("POST / HTTP/1.1\r\nHost: h\r\nContent-Length: 999999999\r\n\r\n");

    expect(fn () => $parser->next())->toThrow(HttpError::class, 'too large');
});

it('keeps a large body off the heap beyond 2 MB', function () {
    $body = str_repeat('x', 3 * 1024 * 1024);
    [$request] = parseAll("POST / HTTP/1.1\r\nHost: h\r\nContent-Length: ".strlen($body)."\r\n\r\n".$body, false, new Limits(body: 4 * 1024 * 1024));

    expect(stream_get_meta_data($request->body)['uri'])->toBe('php://temp/maxmemory:2097152')
        ->and(strlen($request->content()))->toBe(strlen($body));
});
