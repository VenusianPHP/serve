<?php

use Mariner\Auth\Events\Verified;
use Mariner\Auth\User;
use Mariner\Core\Auth\EmailVerificationRequest;
use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\WebApp;

class VerifyingUser extends User
{
    protected $guarded = [];

    public bool $saved = false;

    public function save(array $options = []): bool
    {
        return $this->saved = true;
    }
}

beforeEach(function () {
    $this->app = WebApp::boot();
    config(['session.driver' => 'array']);
    $this->user = new VerifyingUser(['id' => 5, 'email' => 'ada@example.test', 'email_verified_at' => null]);
    $this->app['auth']->guard()->setUser($this->user);
    $this->app['router']->get('/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return 'verified';
    });
    $this->kernel = $this->app->make(Kernel::class);
    $this->heard = [];
    $this->app['signals']->listen(Verified::class, fn (Verified $event) => $this->heard[] = $event->user->id);
});
afterEach(fn () => WebApp::tearDown($this));

it('verifies the signed-in user whose id and email hash the link carries, once', function () {
    $hash = sha1('ada@example.test');

    $first = $this->kernel->handle(Request::create("/verify/5/{$hash}"));
    $second = $this->kernel->handle(Request::create("/verify/5/{$hash}"));

    expect([$first->getStatusCode(), $first->getContent()])->toBe([200, 'verified'])
        ->and($second->getStatusCode())->toBe(200)
        ->and($this->user->hasVerifiedEmail())->toBeTrue()
        ->and($this->user->saved)->toBeTrue()
        ->and($this->heard)->toBe([5]);
});

it('refuses a link for another user or another email', function () {
    expect($this->kernel->handle(Request::create('/verify/6/'.sha1('ada@example.test')))->getStatusCode())->toBe(403)
        ->and($this->kernel->handle(Request::create('/verify/5/'.sha1('eve@example.test')))->getStatusCode())->toBe(403)
        ->and($this->heard)->toBe([]);
});
