<?php

namespace Venusian\Serve\Tests\Integration\Auth;

use Voyager\Database\Testing\RefreshDatabase;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(fn () => $this->app['config']->set('app.key', 'base64:IUHRqAQ99pZ0A1MPjbuv1D6ff3jxv0GIvS2qIW4JNU4='));

beforeEach(function () {
    $router = $this->app['router'];
    $router->post('logout', function (Request $request) {
        auth()->logoutOtherDevices($request->input('password'));

        return response()->noContent();
    })->middleware(['web', 'auth']);
});

test('it rehash the password using logout other devices', function () {
    $this->withoutExceptionHandling();

    $user = UserFactory::new()->create();

    $password = $user->password;

    $this->actingAs($user);

    $this->post('logout', [
        'password' => 'password',
    ])->assertStatus(204);

    $user->refresh();

    expect($user->password)->not->toBe($password);
});
