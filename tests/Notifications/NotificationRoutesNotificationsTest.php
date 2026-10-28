<?php

namespace Venusian\Serve\Tests\Notifications;

use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Notifications\Dispatcher;
use Mariner\Notifications\RoutesNotifications;
use Mariner\NutsAndBolts\Facades\Notification;
use InvalidArgumentException;
use Mockery;
use stdClass;

afterEach(function () {
    ControlPanel::setInstance(null);
});

test('notification can be dispatched', function () {
    $container = new ControlPanel;
    $factory = Mockery::mock(Dispatcher::class);
    $container->registerInstance(Dispatcher::class, $factory);
    $notifiable = new RoutesNotificationsTestInstance;
    $instance = new stdClass;
    $factory->expects('send')->with($notifiable, $instance);
    ControlPanel::setInstance($container);

    $notifiable->notify($instance);
});

test('notification can be sent now', function () {
    $container = new ControlPanel;
    $factory = Mockery::mock(Dispatcher::class);
    $container->registerInstance(Dispatcher::class, $factory);
    $notifiable = new RoutesNotificationsTestInstance;
    $instance = new stdClass;
    $factory->expects('sendNow')->with($notifiable, $instance, null);
    ControlPanel::setInstance($container);

    $notifiable->notifyNow($instance);
});

test('notification option routing', function () {
    $instance = new RoutesNotificationsTestInstance;
    expect($instance->routeNotificationFor('foo'))->toBe('bar');
    expect($instance->routeNotificationFor('mail'))->toBe('taylor@laravel.com');
});

test('on demand notifications cannot use database channel', function () {
    Notification::route('database', 'foo');
})->throws(InvalidArgumentException::class, 'The database channel does not support on-demand notifications.');

class RoutesNotificationsTestInstance
{
    use RoutesNotifications;

    protected $email = 'taylor@laravel.com';

    public function routeNotificationForFoo()
    {
        return 'bar';
    }
}
