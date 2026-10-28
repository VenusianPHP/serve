<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Mariner\Http\Middleware\ConvertEmptyStringsToNull;
use Mariner\Http\Middleware\HandleCors;
use Mariner\Http\Middleware\PreventRequestForgery;
use Mariner\Http\Middleware\TrimStrings;
use Mariner\Http\Middleware\TrustHosts;
use Mariner\Http\Middleware\TrustProxies;
use Mariner\Http\Resources\Json\JsonResource;
use Mariner\Http\Resources\JsonApi\JsonApiResource;
use Mariner\View\Component;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Voyager\NutsAndBolts\Str;

// The statics a test can leave behind: the frozen clock, the trusted proxies, and Str's frozen UUIDs, ULIDs and random strings.
$resetStatics = function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
    SymfonyRequest::setTrustedProxies([], 0);
    Str::createUuidsNormally();
    Str::createUlidsNormally();
    Str::createRandomStringsNormally();

    // The middleware and resource defaults a test can set, as Laravel's TestCase puts them back.
    HandleCors::flushState();
    ConvertEmptyStringsToNull::flushState();
    TrimStrings::flushState();
    TrustHosts::flushState();
    TrustProxies::flushState();
    PreventRequestForgery::flushState();
    JsonResource::flushState();
    JsonApiResource::flushState();

    // The view component caches that tie a component to the app that built them.
    Component::flushCache();
    Component::forgetComponentsResolver();
    Component::forgetFactory();
};

// A test case binds to a directory once, so the unit tests take every directory but Integration, which boots the app.
$unitDirectories = array_map('basename', array_filter(glob(__DIR__.'/*', GLOB_ONLYDIR), fn ($d) => basename($d) !== 'Integration'));

uses(Venusian\Serve\Tests\TestCase::class)->afterEach($resetStatics)->in(...$unitDirectories);

uses(Venusian\Serve\Tests\Integration\IntegrationTestCase::class)->afterEach($resetStatics)->in('Integration');

// Blade compiler tests: a fresh compiler over a mocked filesystem, and the component statics put back.
uses()
    ->beforeEach(function () {
        $this->compiler = new Mariner\View\Compilers\BladeCompiler(Mockery::mock(Voyager\Filesystem\Filesystem::class), __DIR__.'/View/Blade');
    })
    ->afterEach(function () {
        Voyager\Vessel\ControlPanel::setInstance(null);
        Mariner\View\Component::flushCache();
        Mariner\View\Component::forgetComponentsResolver();
        Mariner\View\Component::forgetFactory();
    })
    ->in('View/Blade');
