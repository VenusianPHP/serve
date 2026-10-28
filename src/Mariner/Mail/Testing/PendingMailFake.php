<?php

namespace Mariner\Mail\Testing;

use Mariner\Contracts\Mail\Mailable;
use Mariner\Mail\PendingMail;

class PendingMailFake extends PendingMail
{
    /**
     * Create a new instance.
     *
     * @param  \Mariner\Mail\Testing\MailFake  $mailer
     */
    public function __construct($mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send a new mailable message instance.
     *
     * @param  \Mariner\Contracts\Mail\Mailable  $mailable
     * @return null
     */
    public function send(Mailable $mailable)
    {
        $this->mailer->send($this->fill($mailable));

        return null;
    }

    /**
     * Send a new mailable message instance synchronously.
     *
     * @param  \Mariner\Contracts\Mail\Mailable  $mailable
     * @return null
     */
    public function sendNow(Mailable $mailable)
    {
        $this->mailer->sendNow($this->fill($mailable));

        return null;
    }

    /**
     * Push the given mailable onto the queue.
     *
     * @param  \Mariner\Contracts\Mail\Mailable  $mailable
     * @return mixed
     */
    public function queue(Mailable $mailable)
    {
        return $this->mailer->queue($this->fill($mailable));
    }
}
