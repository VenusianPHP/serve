<?php

namespace Mariner\Mail;

use Mariner\Contracts\Mail\Factory;

/**
 * A mail factory whose mailers send through a worker pool (Mailer::onPool), for callers that take a factory.
 */
class PooledMailers implements Factory
{
    public function __construct(
        private Factory $mailers,
        private ?string $pool = null,
    ) {}

    public function mailer($name = null)
    {
        return $this->mailers->mailer($name)->onPool($this->pool);
    }
}
