<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\Notifications\Notification;

/** A database notification carrying a greeting. */
class Welcome extends Notification
{
    public function __construct(public string $greeting = 'hello') {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return ['greeting' => $this->greeting];
    }
}
