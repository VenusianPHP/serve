<?php

namespace Venusian\Serve\Tests\Notifications;

use Voyager\Broadcasting\PrivateChannel;
use Voyager\Contracts\Signals\SignalDispatcher;
use Mariner\Notifications\Channels\BroadcastChannel;
use Mariner\Notifications\Events\BroadcastNotificationCreated;
use Mariner\Notifications\Messages\BroadcastMessage;
use Mariner\Notifications\Notification;
use Mockery;
use stdClass;

test('database channel creates database record with proper data', function () {
    $notification = new NotificationBroadcastChannelTestNotification;
    $notification->id = 1;
    $notifiable = new stdClass;

    $events = Mockery::mock(SignalDispatcher::class);
    $events->expects('dispatch')->with(Mockery::type(BroadcastNotificationCreated::class));
    $channel = new BroadcastChannel($events);
    $channel->send($notifiable, $notification);
});

test('notification is broadcasted on custom channels', function () {
    $notification = new CustomChannelsTestNotification;
    $notification->id = 1;
    $notifiable = new stdClass;

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $channels = $event->broadcastOn();

    expect($channels[0])->toEqual(new PrivateChannel('custom-channel'));
});

test('notification is broadcasted with custom event name', function () {
    $notification = new CustomEventNameTestNotification;
    $notification->id = 1;
    $notifiable = new stdClass;

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $eventName = $event->broadcastType();

    expect($eventName)->toBe('custom.type');
});

test('notification is broadcasted with custom data type', function () {
    $notification = new CustomEventNameTestNotification;
    $notification->id = 1;
    $notifiable = new stdClass;

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $data = $event->broadcastWith();

    expect($data['type'])->toBe('custom.type');
});

test('notification is broadcasted now', function () {
    $notification = new TestNotificationBroadCastedNow;
    $notification->id = 1;
    $notifiable = new stdClass;

    $events = Mockery::mock(SignalDispatcher::class);
    $events->expects('dispatch')->with(Mockery::on(function ($event) {
        return $event->connection === 'sync';
    }));
    $channel = new BroadcastChannel($events);
    $channel->send($notifiable, $notification);
});

test('notification is broadcasted with custom additional payload', function () {
    $notification = new CustomBroadcastWithTestNotification;
    $notification->id = 1;
    $notifiable = new stdClass;

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $data = $event->broadcastWith();

    expect($data)->toHaveKey('additional');
});

class NotificationBroadcastChannelTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }
}

class CustomChannelsTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastOn()
    {
        return [new PrivateChannel('custom-channel')];
    }
}

class CustomEventNameTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastType()
    {
        return 'custom.type';
    }
}

class TestNotificationBroadCastedNow extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function toBroadcast()
    {
        return (new BroadcastMessage([]))->onConnection('sync');
    }
}

class CustomBroadcastWithTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastWith()
    {
        return ['id' => 1, 'type' => 'custom', 'additional' => 'custom'];
    }
}
