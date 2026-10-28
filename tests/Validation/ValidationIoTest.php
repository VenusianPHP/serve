<?php

use Mariner\Validation\DatabasePresenceVerifier;
use Mariner\Validation\NotPwnedVerifier;
use Mariner\Validation\Rules\Password;
use Mariner\Validation\ValidationException;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\Schema\Blueprint;
use Voyager\Http\Client\Factory as HttpFactory;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-validation-'.bin2hex(random_bytes(4));
    mkdir($this->dir);

    // A database file both this process and the pool's workers open.
    touch($this->dir.'/users.sqlite');
    $this->app['config']->set('database.connections.users', ['driver' => 'sqlite', 'database' => $this->dir.'/users.sqlite', 'prefix' => '']);
    $db = $this->app['db']->connection('users');
    $db->getSchemaBuilder()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
    });
    $db->table('users')->insert([['email' => 'alice@example.test'], ['email' => 'bob@example.test']]);

    $this->queries = 0;
    $db->listen(function () {
        $this->queries++;
    });
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

/** A validator over the users table, as a request's controller makes one. */
function usersValidator($app, array $data, array $rules = ['email' => 'unique:users.users,email', 'friend' => 'exists:users.users,email'])
{
    return $app['validator']->make($data, $rules);
}

// Thread workers need ext-parallel; without it, null picks process workers.
dataset('pools', array_merge([null, 'process'], extension_loaded('parallel') ? ['thread'] : []));

it('keeps blocking validation exactly as it was', function () {
    $validator = usersValidator($this->app, ['email' => 'carol@example.test', 'friend' => 'alice@example.test']);

    expect($validator->passes())->toBeTrue()
        ->and($this->queries)->toBe(2)
        ->and(usersValidator($this->app, ['email' => 'alice@example.test', 'friend' => 'nobody@example.test'])->passes())->toBeFalse();
});

it('runs presence checks on the pool and resolves to the validated data', function (?string $pool) {
    $promise = usersValidator($this->app, ['email' => 'carol@example.test', 'friend' => 'bob@example.test'])->async($pool);

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($this->app['event-loop']->await($promise))->toBe(['email' => 'carol@example.test', 'friend' => 'bob@example.test'])
        ->and($this->queries)->toBe(0);
})->with('pools');

it('rejects with the validation exception and its messages', function (?string $pool) {
    $promise = usersValidator($this->app, ['email' => 'alice@example.test', 'friend' => 'nobody@example.test'])->async($pool);

    try {
        $this->app['event-loop']->await($promise);
        $this->fail('The async validation passed.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['email', 'friend']);
    }
})->with('pools');

it('leaves the original validator and the shared verifier untouched', function () {
    $validator = usersValidator($this->app, ['email' => 'carol@example.test', 'friend' => 'alice@example.test']);
    $this->app['event-loop']->await($validator->async());

    expect($validator->getPresenceVerifier())->toBe($this->app['validation.presence'])
        ->and($validator->passes())->toBeTrue()
        ->and($this->queries)->toBe(2);
});

it('lets two loop tasks overlap their async validations', function () {
    $loop = $this->app['event-loop'];
    $events = [];
    $validate = function (string $email) use ($loop, &$events) {
        $validator = usersValidator($this->app, ['email' => $email, 'friend' => 'alice@example.test']);

        return $loop->async(function () use ($validator, $email, $loop, &$events) {
            $events[] = "{$email}:start";
            $loop->await($validator->async());
            $events[] = "{$email}:done";
        });
    };

    $a = $validate('carol@example.test');
    $b = $validate('dave@example.test');
    $loop->await($a);
    $loop->await($b);

    expect(array_search('dave@example.test:start', $events))->toBeLessThan(array_search('carol@example.test:done', $events));
});

it('checks breached passwords through the async verifier under async validation', function () {
    $sent = [];
    $http = $this->app[HttpFactory::class];
    $http->fake(function ($request) use (&$sent) {
        $sent[] = $request->url();

        // sha1('password') = 5BAA6 1E4C9B93F3F0682250B6CF8331B7EE68FD8
        return HttpFactory::response("1E4C9B93F3F0682250B6CF8331B7EE68FD8:3861493\r\n");
    });
    $this->app->registerInstance(Mariner\Contracts\Validation\UncompromisedVerifier::class, new NotPwnedVerifier($http));

    $validator = $this->app['validator']->make(['password' => 'password'], ['password' => Password::min(4)->uncompromised()]);

    expect(fn () => $this->app['event-loop']->await($validator->async()))->toThrow(ValidationException::class)
        ->and($sent)->toBe(['https://api.pwnedpasswords.com/range/5BAA6']);
});

it('gives each verifier an opt-in copy, leaving the original blocking', function () {
    $verifier = $this->app['validation.presence'];
    $pwned = new NotPwnedVerifier($this->app[HttpFactory::class]);

    expect($verifier->via())->not->toBe($verifier)->toBeInstanceOf(DatabasePresenceVerifier::class)
        ->and($pwned->async())->not->toBe($pwned)->toBeInstanceOf(NotPwnedVerifier::class);
});
