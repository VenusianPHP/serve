<?php

namespace Venusian\Serve\Tests\Fixtures;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** Carries an Email and its Envelope to a worker and answers with what arrived there. */
class EmailEcho implements ShouldPool
{
    public function __construct(public Email $email, public Envelope $envelope) {}

    public function handle(): mixed
    {
        return [
            'subject' => $this->email->getSubject(),
            'from' => $this->envelope->getSender()->toString(),
            'to' => array_map(fn ($a) => $a->toString(), $this->envelope->getRecipients()),
            'text' => $this->email->getTextBody(),
            'html' => $this->email->getHtmlBody(),
            'attachments' => array_map(fn ($p) => [$p->getFilename(), $p->getContentType(), md5($p->getBody())], $this->email->getAttachments()),
            'raw' => strlen($this->email->toString()) > 0,
        ];
    }
}
