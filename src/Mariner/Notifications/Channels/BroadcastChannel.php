<?php

namespace Mariner\Notifications\Channels;

use Voyager\Contracts\Signals\SignalDispatcher;
use Mariner\Notifications\Events\BroadcastNotificationCreated;
use Mariner\Notifications\Messages\BroadcastMessage;
use Mariner\Notifications\Notification;
use RuntimeException;

class BroadcastChannel
{
    /**
     * The event dispatcher.
     *
     * @var \Voyager\Contracts\Signals\SignalDispatcher
     */
    protected $events;

    /**
     * Create a new broadcast channel.
     *
     * @param  \Voyager\Contracts\Signals\SignalDispatcher  $events
     */
    public function __construct(SignalDispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Mariner\Notifications\Notification  $notification
     * @return array|null
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $this->getData($notifiable, $notification);

        $event = new BroadcastNotificationCreated(
            $notifiable, $notification, is_array($message) ? $message : $message->data
        );

        if ($message instanceof BroadcastMessage) {
            $event->onConnection($message->connection)
                ->onQueue($message->queue);
        }

        return $this->events->dispatch($event);
    }

    /**
     * Get the data for the notification.
     *
     * @param  mixed  $notifiable
     * @param  \Mariner\Notifications\Notification  $notification
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toBroadcast')) {
            return $notification->toBroadcast($notifiable);
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new RuntimeException('Notification is missing toBroadcast / toArray method.');
    }
}
