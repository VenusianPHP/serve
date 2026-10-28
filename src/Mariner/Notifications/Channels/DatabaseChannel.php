<?php

namespace Mariner\Notifications\Channels;

use Mariner\Notifications\Notification;
use RuntimeException;

class DatabaseChannel
{
    /**
     * Whether notifications save through a worker pool, and which one.
     */
    protected bool $offloaded = false;

    protected ?string $pool = null;

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Mariner\Notifications\Notification  $notification
     * @return \Voyager\Database\Instrument\Model
     */
    public function send($notifiable, Notification $notification)
    {
        $relation = $notifiable->routeNotificationFor('database', $notification);

        if (! $this->offloaded) {
            return $relation->create($this->buildPayload($notifiable, $notification));
        }

        return tap($relation->make($this->buildPayload($notifiable, $notification)), function ($model) {
            $model->saveAsync($this->pool)->wait();
        });
    }

    /**
     * A copy that saves each notification with the model's saveAsync(), waited on in place: its events
     * run here, its SQL in a worker, and inside a loop task the wait leaves the loop free.
     *
     * @param  string|null  $pool  'thread' or 'process'; null takes the framework's choice
     * @return static
     */
    public function via(?string $pool = null)
    {
        $channel = clone $this;
        $channel->offloaded = true;
        $channel->pool = $pool;

        return $channel;
    }

    /**
     * Build an array payload for the DatabaseNotification Model.
     *
     * @param  mixed  $notifiable
     * @param  \Mariner\Notifications\Notification  $notification
     * @return array
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        return [
            'id' => $notification->id,
            'type' => method_exists($notification, 'databaseType')
                ? $notification->databaseType($notifiable)
                : get_class($notification),
            'data' => $this->getData($notifiable, $notification),
            'read_at' => method_exists($notification, 'initialDatabaseReadAtValue')
                ? $notification->initialDatabaseReadAtValue($notifiable)
                : null,
        ];
    }

    /**
     * Get the data for the notification.
     *
     * @param  mixed  $notifiable
     * @param  \Mariner\Notifications\Notification  $notification
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toDatabase')) {
            return is_array($data = $notification->toDatabase($notifiable))
                ? $data
                : $data->data;
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new RuntimeException('Notification is missing toDatabase / toArray method.');
    }
}
