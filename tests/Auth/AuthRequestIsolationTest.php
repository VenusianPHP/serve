<?php

use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\LoopPause;
use Venusian\Serve\Tests\Fixtures\User;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Database\Schema\Blueprint;

/*
 * Basic auth on one app serving several requests: each request must see only its own credentials.
 * "server" requests each get their own scope, as `php computer serve` gives each its fiber; "same
 * scope" requests share one, as a feature test's calls do.
 */

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->app->registerInstance('env', 'testing');
    $this->dir = sys_get_temp_dir().'/mariner-auth-iso-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    touch($this->dir.'/auth.sqlite');
    $this->app['config']->set('database.connections.auth', ['driver' => 'sqlite', 'database' => $this->dir.'/auth.sqlite', 'prefix' => '']);
    $this->app['config']->set('auth.providers.users', ['driver' => 'instrument', 'model' => User::class]);
    $this->app['config']->set('session.driver', 'array');
    $this->app['db']->connection('auth')->getSchemaBuilder()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->string('password');
        $table->string('remember_token')->nullable();
    });
    $hash = $this->app['hash']->make('secret');
    $this->app['db']->connection('auth')->table('users')->insert([
        ['email' => 'alice@example.test', 'password' => $hash],
        ['email' => 'bob@example.test', 'password' => $hash],
    ]);

    // Basic auth, then a hand-over to the loop so the requests interleave, then who the guard thinks it is.
    $this->app['router']->get('me', function () {
        if ($failed = auth()->guard()->basic('email')) {
            return $failed;
        }

        LoopPause::yield();

        return auth()->user()->email;
    });
    $this->app['router']->get('whoami', fn () => auth()->user()?->email ?? 'guest');
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

function basicAuth(string $email, string $password = 'secret'): array
{
    return ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode("{$email}:{$password}")];
}

/** Send a request through the kernel; in its own scope (a server request) unless $sameScope. */
function serveRequest($app, string $uri, array $server = [], bool $sameScope = false)
{
    if (! $sameScope) {
        $app->forgetScopedInstances();
    }

    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle($request = Request::create($uri, 'GET', [], [], [], $server));
    $kernel->terminate($request, $response);

    return $response;
}

it('gives each interleaved server request only its own user', function () {
    $loop = $this->app['event-loop'];
    $seen = [];
    $request = function (string $email) use ($loop, &$seen) {
        return $loop->async(function () use ($email, &$seen) {
            $response = serveRequest($this->app, '/me', basicAuth($email));
            $seen[$email] = [$response->getStatusCode(), $response->getContent()];
        });
    };

    $alice = $request('alice@example.test');
    $bob = $request('bob@example.test');
    $loop->await($alice);
    $loop->await($bob);

    expect($seen)->toBe([
        'alice@example.test' => [200, 'alice@example.test'],
        'bob@example.test' => [200, 'bob@example.test'],
    ]);
});

it('treats a server request without credentials after an authenticated one as a guest', function () {
    $loop = $this->app['event-loop'];

    $loop->await($loop->async(fn () => serveRequest($this->app, '/me', basicAuth('alice@example.test'))));
    $guest = $loop->await($loop->async(fn () => serveRequest($this->app, '/whoami')));
    $anonymous = $loop->await($loop->async(fn () => serveRequest($this->app, '/me')));

    expect($guest->getContent())->toBe('guest')
        ->and($anonymous->getStatusCode())->toBe(401);
});

it('rejects wrong server credentials without letting the next request in, or out', function () {
    $loop = $this->app['event-loop'];

    $wrong = $loop->await($loop->async(fn () => serveRequest($this->app, '/me', basicAuth('alice@example.test', 'nope'))));
    $right = $loop->await($loop->async(fn () => serveRequest($this->app, '/me', basicAuth('bob@example.test'))));

    expect($wrong->getStatusCode())->toBe(401)
        ->and([$right->getStatusCode(), $right->getContent()])->toBe([200, 'bob@example.test']);
});

it('reads each call\'s own credentials when the calls share one scope, as a feature test\'s do', function () {
    $wrong = serveRequest($this->app, '/me', basicAuth('alice@example.test', 'nope'), sameScope: true);
    $right = serveRequest($this->app, '/me', basicAuth('bob@example.test'), sameScope: true);

    expect($wrong->getStatusCode())->toBe(401)
        ->and([$right->getStatusCode(), $right->getContent()])->toBe([200, 'bob@example.test']);
});

it('hands a closure guard each call\'s own request too', function () {
    $this->app['auth']->viaRequest('header', fn ($request) => $request->header('X-User') ? new Mariner\Auth\GenericUser(['id' => $request->header('X-User')]) : null);
    $this->app['config']->set('auth.guards.header', ['driver' => 'header']);
    $this->app['router']->get('header-user', fn () => auth()->guard('header')->user()?->getAuthIdentifier() ?? 'guest');

    // No user is cached for the first call, so the second must be read from its own request.
    expect(serveRequest($this->app, '/header-user', [], sameScope: true)->getContent())->toBe('guest')
        ->and(serveRequest($this->app, '/header-user', ['HTTP_X_USER' => 'dave'], sameScope: true)->getContent())->toBe('dave');
});

it('gives an already built guard the signal dispatcher that replaced the old one', function () {
    $guard = auth()->guard();
    $original = $guard->getDispatcher();

    $double = new Voyager\Signals\SignalDispatcher($this->app);
    $this->app->registerInstance('signals', $double);

    expect(auth()->guard())->toBe($guard)
        ->and(auth()->guard()->getDispatcher())->toBe($double)
        ->and($double)->not->toBe($original);
});
