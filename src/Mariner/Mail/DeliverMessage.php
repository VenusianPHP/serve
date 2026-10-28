<?php

namespace Mariner\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;
use Voyager\Vessel\ControlPanel;

/**
 * Sends one message from a worker: the transport is built there from the mailer's config,
 * and the Symfony SentMessage crosses back.
 */
class DeliverMessage implements ShouldPool
{
    public function __construct(
        public array $transport,
        public Email $message,
        public Envelope $envelope,
    ) {}

    public function handle(): mixed
    {
        return (new MailManager(ControlPanel::getInstance()))
            ->createSymfonyTransport($this->transport)
            ->send($this->message, $this->envelope);
    }
}
