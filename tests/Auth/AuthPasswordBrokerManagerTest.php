<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Passwords\PasswordBroker;
use Mariner\Auth\Passwords\PasswordBrokerManager;
use Voyager\Config\Repository as Config;
use Voyager\Vessel\ControlPanel;
use Mockery;

test('broker can resolve backed enum', function () {
    $app = getApp();

    $broker = Mockery::mock(PasswordBroker::class);

    $manager = Mockery::mock(PasswordBrokerManager::class, [$app])->makePartial()->shouldAllowMockingProtectedMethods();
    $manager->expects('resolve')->with('users')->andReturn($broker);

    $result1 = $manager->broker(PasswordBrokerName::Users);
    $result2 = $manager->broker('users');

    expect($result1)->toBe($broker);
    expect($result2)->toBe($result1);
});

test('set default driver accepts backed enum', function () {
    $app = getApp();

    $manager = new PasswordBrokerManager($app);
    $manager->setDefaultDriver(PasswordBrokerName::Users);

    expect($app['config']['auth.defaults.passwords'])->toBe('users');
});

function getApp(): ControlPanel
{
    $app = new ControlPanel;

    $app->registerSingleton('config', fn () => new Config([
        'auth' => [
            'defaults' => ['passwords' => 'users'],
            'passwords' => [
                'users' => [
                    'provider' => 'users',
                    'table' => 'password_reset_tokens',
                    'expire' => 60,
                    'throttle' => 60,
                ],
            ],
        ],
    ]));

    return $app;
}

enum PasswordBrokerName: string
{
    case Users = 'users';
}
