<?php

namespace Venusian\Serve\Tests\Notifications\Fixtures;

use Mariner\Notifications\Messages\MailMessage;
use Mariner\Notifications\Notification;

class SentMessageMailNotification extends Notification
{
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('Example notification with attachment.')
            ->attach(dirname(__DIR__, 2).'/Integration/Mail/Fixtures/blank_document.pdf', [
                'as' => 'blank_document.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
