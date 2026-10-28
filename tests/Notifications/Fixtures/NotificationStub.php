<?php

namespace Venusian\Serve\Tests\Notifications\Fixtures;

use Mariner\Notifications\Notification;

class NotificationStub extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }
}
