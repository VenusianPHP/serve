<?php

namespace Venusian\Serve\Tests\Cookie\Middleware;

use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Encryption\Encrypter as EncrypterContract;
use Mariner\Cookie\CookieJar;
use Mariner\Cookie\CookieValuePrefix;
use Mariner\Cookie\Middleware\AddQueuedCookiesToResponse;
use Mariner\Cookie\Middleware\EncryptCookies;
use Voyager\Encryption\Encrypter;
use Voyager\Signals\SignalDispatcher;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\Routing\Controller;
use Mariner\Routing\Router;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function () {
    $this->container = new ControlPanel;
    $this->container->registerSingleton(EncrypterContract::class, function () {
        return new Encrypter(str_repeat('a', 16), 'aes-128-cbc');
    });

    $this->router = new Router(new SignalDispatcher($this->container), $this->container);

    EncryptCookiesTestMiddleware::except(['globally_unencrypted_cookie']);
});

test('set cookie encryption', function () {
    $this->router->get('cookie/set', [
        'middleware' => EncryptCookiesTestMiddleware::class,
        'uses' => EncryptCookiesTestController::class.'@setCookies',
    ]);

    $response = $this->router->dispatch(Request::create('cookie/set', 'GET'));

    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(5);
    expect($cookies[0]->getName())->toBe('encrypted_cookie');
    expect($cookies[0]->getValue())->not->toBe('value');
    expect($cookies[1]->getName())->toBe('encrypted[array_cookie]');
    expect($cookies[1]->getValue())->not->toBe('value');
    expect($cookies[2]->getName())->toBe('encrypted[nested][array_cookie]');
    expect($cookies[3]->getName())->toBe('unencrypted_cookie');
    expect($cookies[3]->getValue())->toBe('value');
    expect($cookies[4]->getName())->toBe('globally_unencrypted_cookie');
    expect($cookies[4]->getValue())->toBe('value');
});

test('queued cookie encryption', function () {
    $this->router->get('cookie/queue', [
        'middleware' => [EncryptCookiesTestMiddleware::class, AddQueuedCookiesToResponseTestMiddleware::class],
        'uses' => EncryptCookiesTestController::class.'@queueCookies',
    ]);

    $response = $this->router->dispatch(Request::create('cookie/queue', 'GET'));

    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(5);
    expect($cookies[0]->getName())->toBe('encrypted_cookie');
    expect($cookies[0]->getValue())->not->toBe('value');
    expect($cookies[1]->getName())->toBe('encrypted[array_cookie]');
    expect($cookies[1]->getValue())->not->toBe('value');
    expect($cookies[2]->getName())->toBe('encrypted[nested][array_cookie]');
    expect($cookies[2]->getValue())->not->toBe('value');
    expect($cookies[3]->getName())->toBe('unencrypted_cookie');
    expect($cookies[3]->getValue())->toBe('value');
    expect($cookies[4]->getName())->toBe('globally_unencrypted_cookie');
    expect($cookies[4]->getValue())->toBe('value');
});

function getEncryptedCookieValue(ControlPanel $container, $key, $value)
{
    $encrypter = $container->make(EncrypterContract::class);

    return $encrypter->encrypt(
        CookieValuePrefix::create($key, $encrypter->getKey()).$value,
        false
    );
}

test('cookie decryption', function () {
    $cookies = [
        'encrypted_cookie' => getEncryptedCookieValue($this->container, 'encrypted_cookie', 'value'),
        'encrypted' => [
            'array_cookie' => getEncryptedCookieValue($this->container, 'encrypted[array_cookie]', 'value'),
            'nested' => [
                'array_cookie' => getEncryptedCookieValue($this->container, 'encrypted[nested][array_cookie]', 'value'),
            ],
        ],
        'unencrypted_cookie' => 'value',
        'globally_unencrypted_cookie' => 'value',
    ];

    $this->container->make(EncryptCookiesTestMiddleware::class)->handle(
        Request::create('/cookie/read', 'GET', [], $cookies),
        function ($request) {
            $cookies = $request->cookies->all();
            expect($cookies)->toHaveCount(4);
            expect($cookies)->toHaveKey('encrypted_cookie');
            expect($cookies['encrypted_cookie'])->toBe('value');
            expect($cookies)->toHaveKey('encrypted');
            expect($cookies['encrypted'])->toHaveKey('array_cookie');
            expect($cookies['encrypted']['array_cookie'])->toBe('value');
            expect($cookies['encrypted'])->toHaveKey('nested');
            expect($cookies['encrypted']['nested'])->toHaveKey('array_cookie');
            expect($cookies['encrypted']['nested']['array_cookie'])->toBe('value');
            expect($cookies)->toHaveKey('unencrypted_cookie');
            expect($cookies['unencrypted_cookie'])->toBe('value');
            expect($cookies)->toHaveKey('globally_unencrypted_cookie');
            expect($cookies['globally_unencrypted_cookie'])->toBe('value');

            return new Response;
        }
    );
});

class EncryptCookiesTestController extends Controller
{
    public function setCookies()
    {
        $response = new Response;
        $response->headers->setCookie(new Cookie('encrypted_cookie', 'value'));
        $response->headers->setCookie(new Cookie('encrypted[array_cookie]', 'value'));
        $response->headers->setCookie(new Cookie('encrypted[nested][array_cookie]', 'value'));
        $response->headers->setCookie(new Cookie('unencrypted_cookie', 'value'));
        $response->headers->setCookie(new Cookie('globally_unencrypted_cookie', 'value'));

        return $response;
    }

    public function queueCookies()
    {
        return new Response;
    }
}

class EncryptCookiesTestMiddleware extends EncryptCookies
{
    protected $except = [
        'unencrypted_cookie',
    ];
}

class AddQueuedCookiesToResponseTestMiddleware extends AddQueuedCookiesToResponse
{
    public function __construct()
    {
        $cookie = new CookieJar;
        $cookie->queue(new Cookie('encrypted_cookie', 'value'));
        $cookie->queue(new Cookie('encrypted[array_cookie]', 'value'));
        $cookie->queue(new Cookie('encrypted[nested][array_cookie]', 'value'));
        $cookie->queue(new Cookie('unencrypted_cookie', 'value'));
        $cookie->queue(new Cookie('globally_unencrypted_cookie', 'value'));

        $this->cookies = $cookie;
    }
}
