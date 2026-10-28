<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Mariner\Http\Middleware\TrustProxies;
use Mariner\Http\Request;

const HEADER_ALL = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PREFIX | Request::HEADER_X_FORWARDED_AWS_ELB;

test('request does not trust', function () {
    $req = createProxiedRequest();

    expect($req->getClientIp())->toBe('192.168.10.10');
    expect($req->getScheme())->toBe('http');
    expect($req->getHost())->toBe('localhost');
    expect($req->getPort())->toEqual(8888);
    expect($req->getBaseUrl())->toBe('');
});

test('does trust trusted proxy', function () {
    $req = createProxiedRequest();
    $req::setTrustedProxies(['192.168.10.10'], HEADER_ALL);

    expect($req->getClientIp())->toBe('173.174.200.38');
    expect($req->getScheme())->toBe('https');
    expect($req->getHost())->toBe('serversforhackers.com');
    expect($req->getPort())->toEqual(443);
    expect($req->getBaseUrl())->toBe('/prefix');
});

test('trusted proxy sets trusted proxies with wildcard', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, '*');
    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
    });
});

test('trusted proxy sets trusted proxies with double wildcard for backwards compat', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, '**');
    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
    });
});

test('trusted proxy sets trusted proxies with wildcard and ipv6 chain', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, '*');
    $request = createProxiedRequest([
        'REMOTE_ADDR' => '2001:db8::1',
        'HTTP_X_FORWARDED_FOR' => '173.174.200.38, 2001:db8::2, 2001:db8::3',
    ]);

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
    });
});

test('trusted proxy sets trusted proxies with remote addr', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, 'REMOTE_ADDR');
    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
    });
});

test('trusted proxy sets trusted proxies', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, ['192.168.10.10']);
    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
    });
});

test('get client ips', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, ['192.168.10.10']);

    $forwardedFor = [
        '192.0.2.2',
        '192.0.2.2, 192.0.2.199',
        '192.0.2.2, 192.0.2.199, 99.99.99.99',
        '192.0.2.2,192.0.2.199',
    ];

    foreach ($forwardedFor as $forwardedForHeader) {
        $request = createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

        $trustedProxy->handle($request, function ($request) use ($forwardedForHeader) {
            $ips = $request->getClientIps();
            expect(end($ips))->toBe('192.0.2.2');
        });
    }
});

test('get client ip with multiple ip addresses some of which are trusted', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, ['192.168.10.10', '192.0.2.199']);

    $forwardedFor = [
        '192.0.2.2',
        '192.0.2.2, 192.0.2.199',
        '99.99.99.99, 192.0.2.2, 192.0.2.199',
        '192.0.2.2,192.0.2.199',
    ];

    foreach ($forwardedFor as $forwardedForHeader) {
        $request = createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

        $trustedProxy->handle($request, function ($request) use ($forwardedForHeader) {
            expect($request->getClientIp())->toBe('192.0.2.2');
        });
    }
});

test('get client ip with multiple ip addresses all proxies are trusted', function () {
    $trustedProxy = createTrustedProxy(HEADER_ALL, '*');

    $forwardedFor = [
        '192.0.2.2',
        '192.0.2.2, 192.0.2.199',
        '192.0.2.2,192.0.2.199',
        '192.0.2.2,99.99.99.99,192.0.2.199',
    ];

    foreach ($forwardedFor as $forwardedForHeader) {
        $request = createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

        $trustedProxy->handle($request, function ($request) use ($forwardedForHeader) {
            expect($request->getClientIp())->toBe('192.0.2.2');
        });
    }
});

test('get client ip with multiple ip addresses all proxies are trusted using forwarded header', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_FORWARDED, '*');

    $forwarded = [
        'for=192.0.2.2',
        'for=192.0.2.2,for=192.0.2.199',
        'for=192.0.2.2,for=99.99.99.99,for=192.0.2.199',
    ];

    foreach ($forwarded as $forwardedHeader) {
        $request = createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader, 'HTTP_X_FORWARDED_FOR' => '']);

        $trustedProxy->handle($request, function ($request) use ($forwardedHeader) {
            expect($request->getClientIp())->toBe('192.0.2.2');
        });
    }
});

test('can distrust headers', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_FORWARDED, ['192.168.10.10']);

    $request = createProxiedRequest([
        'HTTP_FORWARDED' => 'for=173.174.200.40:443; proto=https; host=serversforhackers.com',
        'HTTP_X_FORWARDED_FOR' => '173.174.200.38',
        'HTTP_X_FORWARDED_HOST' => 'svrs4hkrs.com',
        'HTTP_X_FORWARDED_PORT' => '80',
        'HTTP_X_FORWARDED_PROTO' => 'http',
    ]);

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.40');
        expect($request->getScheme())->toBe('https');
        expect($request->getHost())->toBe('serversforhackers.com');
        expect($request->getPort())->toEqual(443);
    });
});

test('x forwarded for header only trusted', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_X_FORWARDED_FOR, '*');

    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
        expect($request->getScheme())->toBe('http');
        expect($request->getHost())->toBe('localhost');
        expect($request->getPort())->toEqual(8888);
        expect($request->getBaseUrl())->toBe('');
    });
});

test('x forwarded host header only trusted', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_X_FORWARDED_HOST, '*');

    $request = createProxiedRequest(['HTTP_X_FORWARDED_HOST' => 'serversforhackers.com:8888']);

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('192.168.10.10');
        expect($request->getScheme())->toBe('http');
        expect($request->getHost())->toBe('serversforhackers.com');
        expect($request->getPort())->toEqual(8888);
        expect($request->getBaseUrl())->toBe('');
    });
});

test('x forwarded port header only trusted', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_X_FORWARDED_PORT, '*');

    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('192.168.10.10');
        expect($request->getScheme())->toBe('http');
        expect($request->getHost())->toBe('localhost');
        expect($request->getPort())->toEqual(443);
        expect($request->getBaseUrl())->toBe('');
    });
});

test('x forwarded prefix header only trusted', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_X_FORWARDED_PREFIX, '*');

    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('192.168.10.10');
        expect($request->getScheme())->toBe('http');
        expect($request->getHost())->toBe('localhost');
        expect($request->getPort())->toEqual(8888);
        expect($request->getBaseUrl())->toBe('/prefix');
    });
});

test('x forwarded proto header only trusted', function () {
    $trustedProxy = createTrustedProxy(Request::HEADER_X_FORWARDED_PROTO, '*');

    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('192.168.10.10');
        expect($request->getScheme())->toBe('https');
        expect($request->getHost())->toBe('localhost');
        expect($request->getPort())->toEqual(8888);
        expect($request->getBaseUrl())->toBe('');
    });
});

test('x forwarded multiple individual headers trusted', function () {
    $trustedProxy = createTrustedProxy(
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        '*'
    );

    $request = createProxiedRequest();

    $trustedProxy->handle($request, function ($request) {
        expect($request->getClientIp())->toBe('173.174.200.38');
        expect($request->getScheme())->toBe('https');
        expect($request->getHost())->toBe('serversforhackers.com');
        expect($request->getPort())->toEqual(443);
        expect($request->getBaseUrl())->toBe('');
    });
});

test('is reading text based configurations', function () {
    $request = createProxiedRequest();

    // trust *all* "X-Forwarded-*" headers
    $trustedProxy = createTrustedProxy('HEADER_X_FORWARDED_ALL', '192.168.1.1, 192.168.1.2');
    $trustedProxy->handle($request, function (Request $request) {
        expect(HEADER_ALL)->toEqual($request->getTrustedHeaderSet());

        expect($request->getTrustedProxies())->toEqual(['192.168.1.1', '192.168.1.2']);
    });

    // or, if your proxy instead uses the "Forwarded" header
    $trustedProxy = createTrustedProxy('HEADER_FORWARDED', '192.168.1.1, 192.168.1.2');
    $trustedProxy->handle($request, function (Request $request) {
        expect($request->getTrustedHeaderSet())->toEqual(Request::HEADER_FORWARDED);

        expect($request->getTrustedProxies())->toEqual(['192.168.1.1', '192.168.1.2']);
    });

    // or, if you're using AWS ELB
    $trustedProxy = createTrustedProxy('HEADER_X_FORWARDED_AWS_ELB', '192.168.1.1, 192.168.1.2');
    $trustedProxy->handle($request, function (Request $request) {
        expect($request->getTrustedHeaderSet())->toEqual(Request::HEADER_X_FORWARDED_AWS_ELB);

        expect($request->getTrustedProxies())->toEqual(['192.168.1.1', '192.168.1.2']);
    });
});

function createProxiedRequest($serverOverrides = [])
{
    // Add some X-Forwarded headers and over-ride
    // defaults, simulating a request made over a proxy
    $serverOverrides = array_replace([
        'HTTP_X_FORWARDED_FOR' => '173.174.200.38',         // X-Forwarded-For    -- getClientIp()
        'HTTP_X_FORWARDED_HOST' => 'serversforhackers.com', // X-Forwarded-Host   -- getHosts()
        'HTTP_X_FORWARDED_PORT' => '443',                   // X-Forwarded-Port   -- getPort()
        'HTTP_X_FORWARDED_PREFIX' => '/prefix',             // X-Forwarded-Prefix -- getBaseUrl()
        'HTTP_X_FORWARDED_PROTO' => 'https',                // X-Forwarded-Proto  -- getScheme() / isSecure()
        'SERVER_PORT' => 8888,
        'HTTP_HOST' => 'localhost',
        'REMOTE_ADDR' => '192.168.10.10',
    ], $serverOverrides);

    // Create a fake request made over "http", one that we'd get over a proxy
    // which is likely something like this:
    $request = Request::create('http://localhost:8888/tag/proxy', 'GET', [], [], [], $serverOverrides, null);
    // Need to make sure these haven't already been set
    $request::setTrustedProxies([], HEADER_ALL);

    return $request;
}

function createTrustedProxy($trustedHeaders, $trustedProxies)
{
    return new class($trustedHeaders, $trustedProxies) extends TrustProxies
    {
        public function __construct($trustedHeaders, $trustedProxies)
        {
            $this->headers = $trustedHeaders;
            $this->proxies = $trustedProxies;
        }
    };
}
