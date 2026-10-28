<?php

namespace Venusian\Serve\Tests\Notifications;

use Voyager\Signals\SignalDispatcher;
use Voyager\Bus\Queueable;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Signals\SignalDispatcher as EventDispatcher;
use Mariner\Notifications\AnonymousNotifiable;
use Mariner\Notifications\ChannelManager;
use Mariner\Notifications\Events\NotificationFailed;
use Mariner\Notifications\Events\NotificationSending;
use Mariner\Notifications\Events\NotificationSent;
use Mariner\Notifications\Notifiable;
use Mariner\Notifications\Notification;
use Mariner\Notifications\NotificationSender;
use Mariner\Notifications\SendQueuedNotifications;
use Voyager\Queue\Attributes\Queue;
use Venusian\Serve\Tests\Fixtures\BusRecorder;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use Venusian\Serve\Tests\Notifications\Fixtures\ChannelSpy;
use Mockery;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

afterEach(function () {
    ControlPanel::setInstance(null);
});

function getManager(): ChannelManager
{
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);

    ControlPanel::setInstance($container);

    return new ChannelManager($container);
}

test('it can send queued notifications with a string via', function () {
    $notifiable = new DummyNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;
    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyQueuedNotificationWithStringVia);

    $bus->assertDispatched(SendQueuedNotifications::class);
});

test('it can send queued notifications with an array via', function () {
    $notifiable = new DummyNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;

    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyQueuedNotificationWithArrayVia);

    $bus->assertDispatchedTimes(SendQueuedNotifications::class, 2);
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'dummy' && $job->channels === ['database'] && $job->connection === 'redis';
    });
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'dummy' && $job->channels === ['mail'] && $job->connection === 'redis';
    });
});

test('it can send notifications with an empty string via', function () {
    $notifiable = new AnonymousNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;
    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->sendNow($notifiable, new DummyNotificationWithEmptyStringVia);

    $bus->assertNothingDispatched();
});

test('it cannot send notifications via database for anonymous notifiables', function () {
    $notifiable = new AnonymousNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;
    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->sendNow($notifiable, new DummyNotificationWithDatabaseVia);

    $bus->assertNothingDispatched();
});

test('it can send queued notifications through middleware', function () {
    $notifiable = new DummyNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;
    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyNotificationWithMiddleware);

    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return ($job->middleware[0] ?? null) instanceof TestNotificationMiddleware;
    });
});

test('it can send queued multi channel notifications through different middleware', function () {
    $notifiable = new DummyNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;
    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyMultiChannelNotificationWithConditionalMiddleware);

    $bus->assertDispatchedTimes(SendQueuedNotifications::class, 3);
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return ($job->middleware[0] ?? null) instanceof TestMailNotificationMiddleware;
    });
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return ($job->middleware[0] ?? null) instanceof TestDatabaseNotificationMiddleware;
    });
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return empty($job->middleware);
    });
});

test('it can send queued with via connections notifications', function () {
    $notifiable = new AnonymousNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;

    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyNotificationWithViaConnections);

    $bus->assertDispatchedTimes(SendQueuedNotifications::class, 2);
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->connection === 'sync' && $job->channels === ['database'] && $job->queue === 'dummy';
    });
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->connection === 'redis' && $job->channels === ['mail'] && $job->queue === 'dummy';
    });
});

test('it can send queued with via queues notifications', function () {
    $notifiable = new AnonymousNotifiable;
    $manager = getManager();
    $bus = new BusRecorder;

    $events = new EventDispatcher;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, new DummyNotificationWithViaQueues);

    $bus->assertDispatchedTimes(SendQueuedNotifications::class, 2);
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'dummy' && $job->channels === ['database'] && $job->connection === 'redis';
    });
    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'admin_notifications' && $job->channels === ['mail'] && $job->connection === 'redis';
    });
});

test('notification failed sent without http transport exception', function () {
    $notifiable = new AnonymousNotifiable;
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $response = Mockery::mock(ResponseInterface::class);
    $driver->exception = new HttpTransportException('Transport error', $response);
    $manager->expects('driver')->andReturn($driver);
    $bus = new BusRecorder;

    $events = new EventDispatcher;
    $failed = null;
    $events->listen(NotificationFailed::class, function ($event) use (&$failed) {
        $failed = $event;
    });

    $sender = new NotificationSender($manager, $bus, $events);

    try {
        $sender->sendNow($notifiable, new DummyNotificationWithViaConnections, ['mail']);
        $this->fail('Expected exception was not thrown.');
    } catch (TransportException) {
        expect($failed->data['exception'])->toBeInstanceOf(TransportException::class);
    }
});

test('it preserves notification state mutated in via method', function () {
    $notifiable = new AnonymousNotifiable;
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $manager->expects('driver')->andReturn($driver);
    $bus = new BusRecorder;

    $events = new SignalRecorder;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->sendNow($notifiable, new DummyNotificationWithViaMutation);

    expect($driver->sent[0][1]->channelData)->toBe('default');
    $events->assertDispatched(NotificationSending::class);
    $events->assertDispatched(NotificationSent::class);
});

test('it queue overrides queue attribute', function () {
    $notification = new #[Queue('attribute-queue')] class extends Notification implements ShouldQueue
    {
        use Queueable;

        public function via($notifiable): string
        {
            return 'mail';
        }
    };

    $notification->onQueue('manual-queue');

    $notifiable = new DummyNotifiable;
    $manager = getManager();

    $events = new EventDispatcher;

    $bus = new BusRecorder;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, $notification);

    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'manual-queue';
    });
});

test('it queue attribute is used when on queue is not called', function () {
    $notification = new #[Queue('attribute-queue')] class extends Notification implements ShouldQueue
    {
        use Queueable;

        public function via($notifiable): string
        {
            return 'mail';
        }
    };

    $notifiable = new DummyNotifiable;
    $manager = getManager();

    $events = new EventDispatcher;

    $bus = new BusRecorder;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, $notification);

    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'attribute-queue';
    });
});

test('it constructor override takes precedence over queue attribute', function () {
    $notification = new #[Queue('attribute-queue')] class extends Notification implements ShouldQueue
    {
        use Queueable;

        public function __construct()
        {
            $this->queue = 'constructor-override-queue';
        }

        public function via($notifiable): string
        {
            return 'mail';
        }
    };

    $notifiable = new DummyNotifiable;
    $manager = getManager();

    $events = new EventDispatcher;

    $bus = new BusRecorder;

    $sender = new NotificationSender($manager, $bus, $events);

    $sender->send($notifiable, $notification);

    $bus->assertDispatched(SendQueuedNotifications::class, function ($job) {
        return $job->queue === 'constructor-override-queue';
    });
});

class DummyQueuedNotificationWithStringVia extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return 'mail';
    }
}

class DummyQueuedNotificationWithArrayVia extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->connection = 'redis';
        $this->queue = 'dummy';
    }

    /**
     * Get the notification channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }
}

class DummyNotificationWithEmptyStringVia extends Notification
{
    use Queueable;

    /**
     * Get the notification channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return '';
    }
}

class DummyNotificationWithDatabaseVia extends Notification
{
    use Queueable;

    /**
     * Get the notification channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return 'database';
    }
}

class DummyNotificationWithViaConnections extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->connection = 'redis';
        $this->queue = 'dummy';
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function viaConnections()
    {
        return [
            'database' => 'sync',
        ];
    }
}

class DummyNotificationWithViaQueues extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->connection = 'redis';
        $this->queue = 'dummy';
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function viaQueues()
    {
        return [
            'mail' => 'admin_notifications',
        ];
    }
}

class DummyNotificationWithMiddleware extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return 'mail';
    }

    public function middleware()
    {
        return [
            new TestNotificationMiddleware,
        ];
    }
}

class DummyMultiChannelNotificationWithConditionalMiddleware extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return [
            'mail',
            'database',
            'broadcast',
        ];
    }

    public function middleware($notifiable, $channel)
    {
        return match ($channel) {
            'mail' => [new TestMailNotificationMiddleware],
            'database' => [new TestDatabaseNotificationMiddleware],
            default => []
        };
    }
}

class TestNotificationMiddleware
{
    public function handle($command, $next)
    {
        return $next($command);
    }
}

class TestMailNotificationMiddleware
{
    public function handle($command, $next)
    {
        return $next($command);
    }
}

class TestDatabaseNotificationMiddleware
{
    public function handle($command, $next)
    {
        return $next($command);
    }
}

class DummyNotificationWithViaMutation extends Notification
{
    public $channelData = null;

    public function via($notifiable)
    {
        $this->channelData = $notifiable->routeConfig ?? 'default';

        return 'mail';
    }
}

class DummyNotifiable
{
    use Notifiable;
}
