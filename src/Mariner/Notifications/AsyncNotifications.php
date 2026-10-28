<?php

namespace Mariner\Notifications;

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/**
 * ChannelManager::async(): the manager's sends, each in a loop task answering with a Promise.
 */
class AsyncNotifications
{
    public function __construct(
        private ChannelManager $manager,
        private Loop $loop,
    ) {}

    /**
     * Send the notification, queueing it when it should be queued.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @return \Voyager\Contracts\IOPools\Promise<null>
     */
    public function send($notifiables, $notification): Promise
    {
        return $this->loop->async(fn () => $this->manager->send($notifiables, $notification));
    }

    /**
     * Send the notification immediately.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return \Voyager\Contracts\IOPools\Promise<null>
     */
    public function sendNow($notifiables, $notification, ?array $channels = null): Promise
    {
        return $this->loop->async(fn () => $this->manager->sendNow($notifiables, $notification, $channels));
    }

    /**
     * Send in the given locale.
     *
     * @param  string  $locale
     * @return static
     */
    public function locale($locale)
    {
        return new static($this->manager->locale($locale), $this->loop);
    }
}
