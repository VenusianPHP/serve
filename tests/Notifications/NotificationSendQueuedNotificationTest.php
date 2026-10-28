<?php

namespace Venusian\Serve\Tests\Notifications;

use LogicException;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Database\ModelIdentifier;
use Mariner\Notifications\AnonymousNotifiable;
use Mariner\Notifications\ChannelManager;
use Mariner\Notifications\Notification;
use Mariner\Notifications\SendQueuedNotifications;
use Voyager\Queue\Attributes\FailOnTimeout;
use Voyager\NutsAndBolts\Collection;
use Venusian\Serve\Tests\Notifications\Fixtures\Models\NotifiableUser;
use Mockery;

afterEach(fn () => ControlPanel::setInstance(null));

test('notifications can be sent', function () {
    $notification = new TestNotification;
    $job = new SendQueuedNotifications('notifiables', $notification);
    $manager = Mockery::mock(ChannelManager::class);
    $manager->expects('sendNow')->withArgs(function ($notifiables, $notification, $channels) {
        return $notifiables instanceof Collection && $notifiables->toArray() === ['notifiables']
            && $notification instanceof TestNotification
            && $channels === null;
    });
    // Voyager's Handleable::handle() takes no arguments, so the job resolves the manager from the container.
    ControlPanel::setInstance($container = new ControlPanel);
    $container->registerInstance(ChannelManager::class, $manager);

    $job->handle();
});

test('a notification cannot be handled directly', function () {
    (new TestNotification)->handle();
})->throws(LogicException::class, 'cannot be handled directly: a queued notification is run by SendQueuedNotifications');

test('serialization of notifiable model', function () {
    $identifier = new ModelIdentifier(NotifiableUser::class, [null], [], null);
    $serializedIdentifier = serialize($identifier);

    $job = new SendQueuedNotifications(new NotifiableUser, new TestNotification);
    $serialized = serialize($job);

    expect($serialized)->toContain($serializedIdentifier);
});

test('serialization of normal notifiable', function () {
    $notifiable = new AnonymousNotifiable;
    $serializedNotifiable = serialize($notifiable);

    $job = new SendQueuedNotifications($notifiable, new TestNotification);
    $serialized = serialize($job);

    expect($serialized)->toContain($serializedNotifiable);
});

test('notification can set max exceptions', function () {
    $notifiable = new NotifiableUser;
    $notification = new class
    {
        public $maxExceptions = 23;
    };

    $job = new SendQueuedNotifications($notifiable, $notification);

    expect($job->maxExceptions)->toEqual(23);
});

test('notification respects fail on timeout attribute', function () {
    $job = new SendQueuedNotifications(new NotifiableUser, new FailOnTimeoutNotification);

    expect($job->failOnTimeout)->toBeTrue();
});

class TestNotification extends Notification
{
}

#[FailOnTimeout]
class FailOnTimeoutNotification extends Notification
{
}
