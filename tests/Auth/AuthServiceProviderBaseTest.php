<?php

use Mariner\Auth\Support\AuthServiceProvider;
use Mariner\Contracts\Auth\Access\Gate;
use Venusian\Serve\Tests\Fixtures\WebApp;

class Invoice {}

class InvoicePolicy {}

class AppAuthServiceProvider extends AuthServiceProvider
{
    protected $policies = [Invoice::class => InvoicePolicy::class];
}

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('registers the app\'s policies with the gate', function () {
    $this->app->register(AppAuthServiceProvider::class);

    expect($this->app->make(Gate::class)->getPolicyFor(Invoice::class))->toBeInstanceOf(InvoicePolicy::class)
        ->and((new AppAuthServiceProvider($this->app))->policies())->toBe([Invoice::class => InvoicePolicy::class]);
});
