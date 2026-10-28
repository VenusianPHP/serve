<?php

namespace Mariner\Notifications\Events;

use Voyager\Bus\Queueable;
use Voyager\Queue\SerializesModels;

class NotificationSkipped
{
    use Queueable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $notifiable  The notifiable entity who would have received the notification.
     * @param  \Mariner\Notifications\Notification  $notification  The notification instance.
     * @param  string  $channel  The channel name.
     */
    public function __construct(
        public $notifiable,
        public $notification,
        public $channel,
    ) {
    }
}
