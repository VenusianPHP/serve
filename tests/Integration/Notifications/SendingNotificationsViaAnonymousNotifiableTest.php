<?php

namespace Venusian\Serve\Tests\Integration\Notifications;

use Mariner\Notifications\AnonymousNotifiable;
use Mariner\Notifications\Notification;
use Mariner\NutsAndBolts\Facades\Notification as NotificationFacade;
use Mariner\Notifications\Testing\NotificationFake;

// The routes the test notification records accumulate in $_SERVER; each test starts without another's.
afterEach(function () {
    unset($_SERVER['__notifiable.route']);
});

test('mail is sent', function () {
    $notifiable = (new AnonymousNotifiable)
        ->route('testchannel', 'enzo')
        ->route('anothertestchannel', 'enzo@deepblue.com');

    NotificationFacade::send(
        $notifiable,
        new TestMailNotificationForAnonymousNotifiable
    );

    expect($_SERVER['__notifiable.route'])->toEqual([
        'enzo', 'enzo@deepblue.com',
    ]);
});

test('anonymous notifiable with multiple routes', function () {
    $_SERVER['__notifiable.route'] = [];

    NotificationFacade::routes([
        'testchannel' => 'enzo',
        'anothertestchannel' => 'enzo@deepblue.com',
    ])->notify(new TestMailNotificationForAnonymousNotifiable());

    expect($_SERVER['__notifiable.route'])->toEqual([
        'enzo', 'enzo@deepblue.com',
    ]);
});

test('faking', function () {
    $fake = NotificationFacade::fake();

    expect($fake)->toBeInstanceOf(NotificationFake::class);

    $notifiable = (new AnonymousNotifiable)
        ->route('testchannel', 'enzo')
        ->route('anothertestchannel', 'enzo@deepblue.com');

    NotificationFacade::locale('it')->send(
        $notifiable,
        new TestMailNotificationForAnonymousNotifiable
    );

    NotificationFacade::assertSentTo(new AnonymousNotifiable, TestMailNotificationForAnonymousNotifiable::class,
        function ($notification, $channels, $notifiable, $locale) {
            return $notifiable->routes['testchannel'] === 'enzo' &&
                $notifiable->routes['anothertestchannel'] === 'enzo@deepblue.com' &&
                $locale === 'it';
        }
    );
});

class TestMailNotificationForAnonymousNotifiable extends Notification
{
    public function via($notifiable)
    {
        return [TestCustomChannel::class, AnotherTestCustomChannel::class];
    }
}

class TestCustomChannel
{
    public function send($notifiable, $notification)
    {
        $_SERVER['__notifiable.route'][] = $notifiable->routeNotificationFor('testchannel');
    }
}

class AnotherTestCustomChannel
{
    public function send($notifiable, $notification)
    {
        $_SERVER['__notifiable.route'][] = $notifiable->routeNotificationFor('anothertestchannel');
    }
}
