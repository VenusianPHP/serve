<?php

namespace Venusian\Serve\Tests\Notifications;

use Exception;
use Voyager\Bus\Queueable;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Bus\Dispatcher as Bus;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Signals\SignalDispatcher as EventsDispatcher;
use Mariner\Notifications\ChannelManager;
use Mariner\Notifications\Events\NotificationFailed;
use Mariner\Notifications\Events\NotificationSending;
use Mariner\Notifications\Events\NotificationSent;
use Mariner\Notifications\Events\NotificationSkipped;
use Mariner\Notifications\Notifiable;
use Mariner\Notifications\Notification;
use Mariner\Notifications\SendQueuedNotifications;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\SerializesModels;
use Venusian\Serve\Tests\Fixtures\BusRecorder;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use Venusian\Serve\Tests\Notifications\Fixtures\ChannelSpy;
use Laravel\SerializableClosure\SerializableClosure;
use Mockery;
use stdClass;

afterEach(function () {
    ControlPanel::setInstance(null);
});

test('notification can be dispatched to driver', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $manager->expects('driver')->andReturn($driver);

    $manager->send($notifiable = new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);

    expect($driver->sent)->toHaveCount(1);
    expect($driver->sent[0][0])->toBe($notifiable);
    expect($driver->sent[0][1])->toBeInstanceOf(NotificationChannelManagerTestNotification::class);
    $events->assertDispatchedOnce(NotificationSending::class);
    $events->assertDispatchedOnce(NotificationSent::class);
});

test('channel can be resolved using backed enum', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);

    $manager = new ChannelManager($container);
    $manager->extend('test', fn () => new NotificationChannelManagerTestCustomChannel);

    expect($manager->channel(NotificationChannelManagerTestChannelEnum::Test))->toBeInstanceOf(NotificationChannelManagerTestCustomChannel::class);
});

test('driver can be resolved using backed enum', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);

    $manager = new ChannelManager($container);

    expect($manager->driver(NotificationChannelManagerTestChannelEnum::Custom))->toBeInstanceOf(NotificationChannelManagerTestCustomChannel::class);
});

test('notification not sent on halt', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new EventsDispatcher;
    $container->registerInstance(SignalDispatcher::class, $events);
    $calls = 0;
    $events->listen(NotificationSending::class, function () use (&$calls) {
        return $calls++ > 0;
    });
    $skipped = [];
    $events->listen(NotificationSkipped::class, function ($event) use (&$skipped) {
        $skipped[] = $event;
    });
    $sent = [];
    $events->listen(NotificationSent::class, function ($event) use (&$sent) {
        $sent[] = $event;
    });
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $manager->expects('driver')->andReturn($driver);

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestNotificationWithTwoChannels);

    expect($driver->sent)->toHaveCount(1);
    expect($skipped)->toHaveCount(1);
    expect($sent)->toHaveCount(1);
});

test('notification not sent when cancelled', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $manager->shouldNotReceive('driver');

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestCancelledNotification);

    $events->assertDispatchedOnce(NotificationSkipped::class);
    $events->assertNotDispatched(NotificationSent::class);
});

test('notification sent when not cancelled', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $manager->expects('driver')->andReturn($driver);

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestNotCancelledNotification);

    expect($driver->sent)->toHaveCount(1);
    $events->assertDispatchedOnce(NotificationSending::class);
    $events->assertDispatchedOnce(NotificationSent::class);
});

test('notification not sent when failed', function () {

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $driver->exception = new Exception();
    $manager->expects('driver')->andReturn($driver);

    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);

    $events->assertDispatchedOnce(NotificationSending::class);
    $events->assertDispatchedOnce(NotificationFailed::class);
    $events->assertNotDispatched(NotificationSent::class);
})->throws(Exception::class);

test('notification failed dispatched only once when failed', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new EventsDispatcher;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new class($events)
    {
        public function __construct(private $events)
        {
        }

        public function send($notifiable, $notification)
        {
            $this->events->dispatch(new NotificationFailed($notifiable, $notification, 'test'));

            throw new Exception();
        }
    };
    $manager->expects('driver')->andReturn($driver);
    $failed = 0;
    $events->listen(NotificationFailed::class, function () use (&$failed) {
        $failed++;
    });
    $sent = 0;
    $events->listen(NotificationSent::class, function () use (&$sent) {
        $sent++;
    });

    try {
        $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
        $this->fail('Expected exception was not thrown.');
    } catch (Exception) {
        expect($failed)->toBe(1);
        expect($sent)->toBe(0);
    }
});

test('notification failed dispatched only once when multiple failed', function () {

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new EventsDispatcher;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = $container->make(ChannelManager::class, ['container' => $container]);
    $manager->extend('test', function () {
        return new class
        {
            private $count = 0;

            public function send($notifiable, Notification $notification)
            {
                if ($this->count > 1) {
                    throw new \Exception();
                }

                $this->count++;
            }
        };
    });
    $failed = 0;
    $events->listen(NotificationFailed::class, function () use (&$failed) {
        $failed++;
    });
    $sent = 0;
    $events->listen(NotificationSent::class, function () use (&$sent) {
        $sent++;
    });

    try {
        $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
        $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
        $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
    } finally {
        expect($sent)->toBe(2);
        expect($failed)->toBe(1);
    }
})->throws(Exception::class);

test('notification can be queued', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestQueuedNotification);

    $bus->assertDispatched(SendQueuedNotifications::class);
});

test('send queued notifications can be override via container', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $container->bind(SendQueuedNotifications::class, TestSendQueuedNotifications::class);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestQueuedNotification);

    $bus->assertDispatched(TestSendQueuedNotifications::class);
});

test('queued notification forwards message group from method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    $notification = $this->getMockBuilder(NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod::class)->onlyMethods(['messageGroup'])->getMock();
    $notification->expects($this->exactly(2))->method('messageGroup')->willReturn($mockedMessageGroupId);

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(fn ($job) => expect($job->messageGroup)->toEqual($mockedMessageGroupId));
});

test('queued notification forwards message group from property overriding method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    // Ensure the messageGroup method is not called when a messageGroup property is provided.
    $notification = $this->getMockBuilder(NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod::class)->onlyMethods(['messageGroup'])->getMock();
    $notification->expects($this->never())->method('messageGroup')->willReturn('this-should-not-be-used');
    $notification->onGroup($mockedMessageGroupId);

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(fn ($job) => expect($job->messageGroup)->toEqual($mockedMessageGroupId));
});

test('queued notification forwards message group set to queue job', function () {
    $mockedMessageGroupSet = [
        'test' => 'group-1',
        'test2' => 'group-2',
    ];

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithTwoChannels)->onGroup($mockedMessageGroupSet);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(fn ($job) => expect($job->messageGroup)->toEqual($mockedMessageGroupSet[$job->channels[0]]));
});

test('queued notification forwards message group set from class to queue job', function () {
    $mockedMessageGroupSet = [
        'test' => 'group-1',
        'test2' => 'group-2',
    ];

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithMessageGroups);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(fn ($job) => expect($job->messageGroup)->toEqual($mockedMessageGroupSet[$job->channels[0]]));
});

test('queued notification forwards deduplicator to queue job', function () {
    $mockedDeduplicator = fn ($payload, $queue) => 'deduplication-id-1';

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotification)->withDeduplicator($mockedDeduplicator);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $job = $bus->dispatched(SendQueuedNotifications::class)->sole();
    expect($job->deduplicator)->toBeInstanceOf(SerializableClosure::class);
    expect($job->deduplicator->getClosure())->toEqual($mockedDeduplicator);
});

test('queued notification forwards deduplicator set to queue job', function () {
    $mockedDeduplicatorSet = [
        'test' => fn ($payload, $queue) => 'deduplication-id-1',
        'test2' => fn ($payload, $queue) => 'deduplication-id-2',
    ];

    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithTwoChannels)->withDeduplicator($mockedDeduplicatorSet);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(function ($job) use ($mockedDeduplicatorSet) {
        expect($job->deduplicator)->toBeInstanceOf(SerializableClosure::class);
        expect($job->deduplicator->getClosure())->toEqual($mockedDeduplicatorSet[$job->channels[0]]);
    });
});

test('queued notification forwards deduplicator set from class to queue job', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithDeduplicators);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(fn ($job) => expect(call_user_func($job->deduplicator, '', null))->toEqual($job->notification->deduplicatorResults[$job->channels[0]]));
});

test('queued notification forwards deduplication id method to queue job', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    ControlPanel::setInstance($container);
    $manager = new ChannelManager($container);

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithDeduplicationId);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);

    $jobs = $bus->dispatched(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    $jobs->each(function ($job) {
        expect($job->deduplicator)->toBeInstanceOf(SerializableClosure::class);
        expect(($job->deduplicator->getClosure())('payload', 'queue'))->toBe($job->notification->deduplicationId('payload', 'queue'));
    });
});

test('after sending method after sending notification', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $bus = new BusRecorder;
    $container->registerInstance(Bus::class, $bus);
    $events = new SignalRecorder;
    $container->registerInstance(SignalDispatcher::class, $events);
    ControlPanel::setInstance($container);
    $manager = Mockery::mock(ChannelManager::class.'[driver]', [$container]);
    $driver = new ChannelSpy;
    $manager->expects('driver')->andReturn($driver);
    $driver->response = $response = new stdClass;

    $manager->send($notifiable = new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerWithAfterSendingMethodNotification);

    expect(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingNotifiable)->toBe($notifiable);
    expect(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingChannel)->toBe('test');
    expect(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingResponse)->toBe($response);

    $events->assertDispatchedOnce(NotificationSending::class);
    $events->assertDispatchedOnce(NotificationSent::class);
});

class TestSendQueuedNotifications implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): mixed
    {
        return null;
    }
}

class NotificationChannelManagerTestNotifiable
{
    use Notifiable;
}

class NotificationChannelManagerTestNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestNotificationWithTwoChannels extends Notification
{
    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestCancelledNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function shouldSend($notifiable, $channel)
    {
        return false;
    }
}

class NotificationChannelManagerTestNotCancelledNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function shouldSend($notifiable, $channel)
    {
        return true;
    }
}

class NotificationChannelManagerTestQueuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestQueuedNotificationWithTwoChannels extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function messageGroup()
    {
        return 'group-1';
    }
}

class NotificationChannelManagerTestQueuedNotificationWithMessageGroups extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function withMessageGroups($notifiable, $channel)
    {
        return match ($channel) {
            'test' => 'group-1',
            'test2' => 'group-2',
            default => null,
        };
    }
}

class NotificationChannelManagerTestQueuedNotificationWithDeduplicators extends Notification implements ShouldQueue
{
    use Queueable;

    public $deduplicatorResults = [
        'test' => 'deduplication-id-1',
        'test2' => 'deduplication-id-2',
    ];

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function withDeduplicators($notifiable, $channel)
    {
        return match ($channel) {
            'test' => fn ($payload, $queue) => $this->deduplicatorResults['test'],
            'test2' => fn ($payload, $queue) => $this->deduplicatorResults['test2'],
            default => null,
        };
    }
}

class NotificationChannelManagerTestQueuedNotificationWithDeduplicationId extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function deduplicationId($payload, $queue)
    {
        return 'deduplication-id-1';
    }
}

class NotificationChannelManagerWithAfterSendingMethodNotification extends Notification
{
    public static $afterSendingNotifiable;
    public static $afterSendingChannel;
    public static $afterSendingResponse;

    public function via()
    {
        return ['test'];
    }

    public function afterSending($notifiable, $channel, $response)
    {
        static::$afterSendingNotifiable = $notifiable;
        static::$afterSendingChannel = $channel;
        static::$afterSendingResponse = $response;
    }
}

enum NotificationChannelManagerTestChannelEnum: string
{
    case Test = 'test';
    case Custom = NotificationChannelManagerTestCustomChannel::class;
}

class NotificationChannelManagerTestCustomChannel
{
    public function send($notifiable, $notification)
    {
        //
    }
}
