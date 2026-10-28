<?php

namespace Venusian\Serve\Tests\Notifications\Fixtures;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Mariner\Notifications\Notification;
use Voyager\Queue\Attributes\DeleteWhenMissingModels;
use Voyager\Queue\SerializesModels;
use Venusian\Serve\Tests\Integration\Queue\DeleteNotificationTestModel;

#[DeleteWhenMissingModels]
class DeleteWhenMissingNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public static bool $sent = false;

    public function __construct(public DeleteNotificationTestModel $model)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        static::$sent = true;

        return new \Mariner\Notifications\Messages\MailMessage;
    }
}
