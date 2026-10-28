<?php

namespace Mariner\Notifications;

use Voyager\Contracts\Bus\Dispatcher as Bus;
use Voyager\Contracts\Signals\SignalDispatcher;
use Mariner\Contracts\Notifications\Dispatcher as DispatcherContract;
use Mariner\Contracts\Notifications\Factory as FactoryContract;
use Voyager\NutsAndBolts\Manager;
use Voyager\NutsAndBolts\Concerns\Macroable;
use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

class ChannelManager extends Manager implements DispatcherContract, FactoryContract
{
    use Macroable;


    /**
     * The default channel used to deliver messages.
     *
     * @var string
     */
    protected $defaultChannel = 'mail';

    /**
     * The locale used when sending notifications.
     *
     * @var string|null
     */
    protected $locale;

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        $this->resolveNotificationSender()->send($notifiables, $notification);
    }

    /**
     * Send the given notification immediately.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        $this->resolveNotificationSender()->sendNow($notifiables, $notification, $channels);
    }

    /**
     * Get a channel instance.
     *
     * @param  \UnitEnum|string|null  $name
     * @return mixed
     */
    public function channel($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Get a driver instance.
     *
     * @param  \UnitEnum|string|null  $driver
     * @return mixed
     */
    public function driver(UnitEnum|string|null $driver = null): mixed
    {
        if ($driver instanceof UnitEnum) {
            $driver = $driver instanceof BackedEnum ? $driver->value : $driver->name;
        }

        return parent::driver($driver);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Mariner\Notifications\Channels\DatabaseChannel
     */
    protected function createDatabaseDriver()
    {
        return $this->vessel->make(Channels\DatabaseChannel::class);
    }

    /**
     * Create an instance of the broadcast driver.
     *
     * @return \Mariner\Notifications\Channels\BroadcastChannel
     */
    protected function createBroadcastDriver()
    {
        return $this->vessel->make(Channels\BroadcastChannel::class);
    }

    /**
     * Create an instance of the mail driver.
     *
     * @return \Mariner\Notifications\Channels\MailChannel
     */
    protected function createMailDriver()
    {
        return $this->vessel->make(Channels\MailChannel::class);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver(string $driver): mixed
    {
        try {
            return parent::createDriver($driver);
        } catch (InvalidArgumentException $e) {
            if (class_exists($driver)) {
                return $this->vessel->make($driver);
            }

            throw $e;
        }
    }

    /**
     * Resolve the NotificationSender instance.
     *
     * @return \Mariner\Notifications\NotificationSender
     */
    protected function resolveNotificationSender()
    {
        // A sender per send: the locale travels with it, so concurrent sends never share one.
        return (new NotificationSender(
            $this, $this->vessel->make(Bus::class), $this->vessel->make(SignalDispatcher::class)
        ))->locale($this->locale);
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): ?string
    {
        return $this->defaultChannel;
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function deliversVia()
    {
        return $this->getDefaultDriver();
    }

    /**
     * Set the default channel driver name.
     *
     * @param  string  $channel
     * @return void
     */
    public function deliverVia($channel)
    {
        $this->defaultChannel = $channel;
    }

    /**
     * Send on the event loop: each send runs in a loop task and answers with a Promise. The database
     * channel saves with the model's saveAsync() and the mail channel sends through a worker, so the
     * loop stays free while they wait.
     *
     * @param  string|null  $pool  'thread' or 'process'; null takes the framework's choice
     */
    public function async(?string $pool = null): AsyncNotifications
    {
        // A copy holds the pooled channels, so the shared manager's channels stay as they are.
        $manager = clone $this;
        $manager->drivers['database'] = $this->driver('database')->via($pool);
        $manager->drivers['mail'] = $this->driver('mail')->via($pool);

        return new AsyncNotifications($manager, $this->vessel->make('event-loop'));
    }

    /**
     * Set the locale of notifications.
     *
     * @param  string  $locale
     * @return static
     */
    public function locale($locale)
    {
        // A copy carries the locale, so the shared manager never holds one request's choice.
        $manager = clone $this;
        $manager->locale = $locale;

        return $manager;
    }
}
