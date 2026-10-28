<?php

namespace Mariner\Notifications;

use Voyager\Queue\SerializesModels;

class Notification
{
    use SerializesModels;

    /**
     * The unique identifier for the notification.
     *
     * @var string
     */
    public $id;

    /**
     * The locale to be used when sending the notification.
     *
     * @var string|null
     */
    public $locale;

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }

    /**
     * Set the locale to send this notification in.
     *
     * @param  string  $locale
     * @return $this
     */
    public function locale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Satisfy Voyager's ShouldQueue contract, which every queued class must handle().
     *
     * A queued notification is run by SendQueuedNotifications, which sends it; handling it directly is a mistake.
     *
     * @throws \LogicException
     */
    public function handle(): mixed
    {
        throw new \LogicException(sprintf(
            '[%s] cannot be handled directly: a queued notification is run by SendQueuedNotifications, which sends it.',
            static::class
        ));
    }
}
