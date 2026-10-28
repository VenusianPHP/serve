<?php

namespace Mariner\Mail;

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/**
 * Mailer::via(): the mailer's sends, each in a loop task answering with a Promise, the message
 * going out through a worker pool.
 */
class AsyncMailer
{
    public function __construct(
        private Mailer $mailer,
        private Loop $loop,
    ) {}

    /**
     * Send a new message using a view, or a mailable.
     *
     * @param  \Mariner\Contracts\Mail\Mailable|string|array  $view
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @return \Voyager\Contracts\IOPools\Promise<\Mariner\Mail\SentMessage|null>
     */
    public function send($view, array $data = [], $callback = null): Promise
    {
        return $this->loop->async(fn () => $this->mailer->send($view, $data, $callback));
    }

    /**
     * Send a new message immediately, even a mailable that should queue.
     *
     * @param  \Mariner\Contracts\Mail\Mailable|string|array  $mailable
     * @param  array  $data
     * @param  \Closure|string|null  $callback
     * @return \Voyager\Contracts\IOPools\Promise<\Mariner\Mail\SentMessage|null>
     */
    public function sendNow($mailable, array $data = [], $callback = null): Promise
    {
        return $this->loop->async(fn () => $this->mailer->sendNow($mailable, $data, $callback));
    }
}
