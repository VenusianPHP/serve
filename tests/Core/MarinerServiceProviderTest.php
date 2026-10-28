<?php

use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('resolves every web service once Mariner is registered', function (string $abstract) {
    expect($this->app->make($abstract))->toBeObject();
})->with([
    'router', 'url', 'redirect', 'session', 'session.store', 'cookie', 'auth', 'auth.password',
    'translator', 'validator', 'view', 'blade.compiler', 'mail.manager', 'image',
]);

it('binds the web paths under the base path', function () {
    $base = $this->app->basePath();

    expect(app('path.public'))->toBe($base.DIRECTORY_SEPARATOR.'public')
        ->and(app('path.resources'))->toBe($base.DIRECTORY_SEPARATOR.'resources')
        ->and(app('path.lang'))->toBe($base.DIRECTORY_SEPARATOR.'lang')
        ->and(public_path('index.php'))->toBe($base.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php');
});

it('keeps the web app keys under webapp, leaving the application\'s app config alone', function () {
    expect(config('webapp.locale'))->toBe('en')
        ->and(config('webapp.url'))->toBe('http://localhost')
        ->and(config('webapp.maintenance.driver'))->toBe('file')
        ->and(config('app.name'))->toBe('Venusian')
        ->and(config()->has('app.url'))->toBeFalse()
        ->and(config()->has('app.locale'))->toBeFalse();
});

it('publishes webapp.php with the other configs', function () {
    $published = Mariner\Core\Providers\MarinerServiceProvider::pathsToPublish(
        Mariner\Core\Providers\MarinerServiceProvider::class, 'mariner-config'
    );

    expect(array_map('basename', $published))->toContain('webapp.php')->not->toContain('app.php');
});

it('resolves every aliased class and contract to its web service', function (string $abstract, string $alias) {
    expect($this->app->make($alias))->toBe($this->app->make($abstract))
        ->and($this->app->make($alias))->toBeInstanceOf($alias);
})->with(function () {
    foreach (Mariner\Core\Providers\MarinerServiceProvider::ALIASES as $abstract => $aliases) {
        foreach ($aliases as $alias) {
            yield "{$abstract} as {$alias}" => [$abstract, $alias];
        }
    }
});

it('builds request-state services that never fall back to process-wide state', function (string $abstract, string $resolver) {
    $service = $this->app->make($abstract);

    expect((fn () => $this->{$resolver})->call($service))->toBeInstanceOf(Closure::class);
})->with([
    'cookie jar queue' => ['cookie', 'queueResolver'],
    'translator locale' => ['translator', 'localeStateResolver'],
    'view render state' => ['view', 'renderStateResolver'],
    'view request shares' => ['view', 'handlingRequestResolver'],
]);
