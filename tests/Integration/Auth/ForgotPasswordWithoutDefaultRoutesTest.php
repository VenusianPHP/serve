<?php

namespace Venusian\Serve\Tests\Integration\Auth;

use Mariner\Auth\Notifications\ResetPassword;
use Voyager\Database\Testing\RefreshDatabase;
use Mariner\Notifications\Messages\MailMessage;
use Mariner\NutsAndBolts\Facades\Notification;
use Mariner\NutsAndBolts\Facades\Password;
use Voyager\NutsAndBolts\Str;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\AuthenticationTestUser;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

afterEach(function () {
    ResetPassword::$createUrlCallback = null;
    ResetPassword::$toMailCallback = null;

});

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('app.key', Str::random(32));
    $app['config']->set('auth.providers.users.model', AuthenticationTestUser::class);
});

beforeEach(function () {
    $router = $this->app['router'];
    $router->get('custom/password/reset/{token}', function ($token) {
        return 'Custom reset password!';
    })->name('custom.password.reset');
});

test('it cannot send forgot password email', function () {

    Notification::fake();

    UserFactory::new()->create();

    $user = AuthenticationTestUser::first();

    Password::broker()->sendResetLink([
        'email' => $user->email,
    ]);

    Notification::assertSentTo(
        $user,
        function (ResetPassword $notification, $channels) use ($user) {
            $message = $notification->toMail($user);

            return ! is_null($notification->token)
                && $message->actionUrl === route('custom.password.reset', ['token' => $notification->token, 'email' => $user->email]);
        }
    );
})->throws(RouteNotFoundException::class, 'Route [password.reset] not defined.');

test('it can send forgot password email via create url using', function () {
    Notification::fake();

    ResetPassword::createUrlUsing(function ($user, string $token) {
        return route('custom.password.reset', $token);
    });

    UserFactory::new()->create();

    $user = AuthenticationTestUser::first();

    Password::broker()->sendResetLink([
        'email' => $user->email,
    ]);

    Notification::assertSentTo(
        $user,
        function (ResetPassword $notification, $channels) use ($user) {
            $message = $notification->toMail($user);

            return ! is_null($notification->token)
                && $message->actionUrl === route('custom.password.reset', ['token' => $notification->token]);
        }
    );
});

test('it can send forgot password email via to mail using', function () {
    Notification::fake();

    ResetPassword::toMailUsing(function ($notifiable, $token) {
        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), route('custom.password.reset', $token))
            ->line(__('If you did not request a password reset, no further action is required.'));
    });

    UserFactory::new()->create();

    $user = AuthenticationTestUser::first();

    Password::broker()->sendResetLink([
        'email' => $user->email,
    ]);

    Notification::assertSentTo(
        $user,
        function (ResetPassword $notification, $channels) use ($user) {
            $message = $notification->toMail($user);

            return ! is_null($notification->token)
                && $message->actionUrl === route('custom.password.reset', ['token' => $notification->token]);
        }
    );
});
