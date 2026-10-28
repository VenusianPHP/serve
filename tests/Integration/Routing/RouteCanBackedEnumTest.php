<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Gate;
use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\AbilityBackedEnum;
use User;

test('simple route with string backed enum can ability guest forbidden through the framework', function () {
    $gate = Gate::define(AbilityBackedEnum::NotAccessRoute, fn (?User $user) => false);
    expect($gate->abilities())->toHaveKey('not-access-route');

    $route = Route::get('/', function () {
        return 'Hello World';
    })->can(AbilityBackedEnum::NotAccessRoute);
    expect($route->middleware())->toEqual(['can:not-access-route']);

    $response = $this->get('/');
    $response->assertForbidden();
});

test('simple route with string backed enum can ability guest allowed through the framework', function () {
    $gate = Gate::define(AbilityBackedEnum::AccessRoute, fn (?User $user) => true);
    expect($gate->abilities())->toHaveKey('access-route');

    $route = Route::get('/', function () {
        return 'Hello World';
    })->can(AbilityBackedEnum::AccessRoute);
    expect($route->middleware())->toEqual(['can:access-route']);

    $response = $this->get('/');
    $response->assertOk();
    $response->assertContent('Hello World');
});
