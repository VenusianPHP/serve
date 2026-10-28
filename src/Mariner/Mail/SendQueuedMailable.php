<?php

namespace Mariner\Mail;

use Voyager\Bus\Queueable;
use Mariner\Contracts\Mail\Factory as MailFactory;
use Mariner\Contracts\Mail\Mailable as MailableContract;
use Voyager\Contracts\Queue\ShouldBeEncrypted;
use Voyager\Contracts\Queue\ShouldQueueAfterCommit;
use Voyager\Queue\Attributes\Backoff;
use Voyager\Queue\Attributes\Connection;
use Voyager\Queue\Attributes\DeleteWhenMissingModels;
use Voyager\Queue\Attributes\FailOnTimeout;
use Voyager\Queue\Attributes\MaxExceptions;
use Voyager\Queue\Attributes\Queue as QueueAttribute;
use Voyager\Queue\Attributes\ReadsQueueAttributes;
use Voyager\Queue\Attributes\Timeout;
use Voyager\Queue\Attributes\Tries;
use Voyager\Queue\InteractsWithQueue;

class SendQueuedMailable
{
    use InteractsWithQueue, Queueable, ReadsQueueAttributes;

    /**
     * The mailable message instance.
     *
     * @var \Mariner\Contracts\Mail\Mailable
     */
    public $mailable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int|null
     */
    public $maxExceptions;

    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Indicates if the job should be deleted when models are missing.
     */
    public bool $deleteWhenMissingModels = false;

    /**
     * Indicates if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = false;

    /**
     * Create a new job instance.
     *
     * @param  \Mariner\Contracts\Mail\Mailable  $mailable
     */
    public function __construct(MailableContract $mailable)
    {
        $this->mailable = $mailable;

        if ($mailable instanceof ShouldQueueAfterCommit) {
            $this->afterCommit = true;
        } else {
            $this->afterCommit = property_exists($mailable, 'afterCommit') ? $mailable->afterCommit : null;
        }

        $this->connection = $this->getAttributeValue($mailable, Connection::class, 'connection');
        $this->maxExceptions = $this->getAttributeValue($mailable, MaxExceptions::class, 'maxExceptions');
        $this->queue = $this->getAttributeValue($mailable, QueueAttribute::class, 'queue');
        $this->shouldBeEncrypted = $mailable instanceof ShouldBeEncrypted;
        $this->timeout = $this->getAttributeValue($mailable, Timeout::class, 'timeout');
        $this->tries = $this->getAttributeValue($mailable, Tries::class, 'tries');
        $this->deleteWhenMissingModels = $this->getAttributeValue($mailable, DeleteWhenMissingModels::class, 'deleteWhenMissingModels') ?? false;
        $this->failOnTimeout = $this->getAttributeValue($mailable, FailOnTimeout::class, 'failOnTimeout') ?? false;
    }

    /**
     * Handle the queued job.
     *
     * @param  \Mariner\Contracts\Mail\Factory  $factory
     * @return void
     */
    public function handle(MailFactory $factory)
    {
        $this->mailable->send($factory);
    }

    /**
     * Get the number of seconds before a released mailable will be available.
     *
     * @return mixed
     */
    public function backoff()
    {
        $backoff = $this->getAttributeValue($this->mailable, Backoff::class, 'backoff');

        if (method_exists($this->mailable, 'backoff')) {
            $backoff = $this->mailable->backoff();
        }

        return $backoff;
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime|null
     */
    public function retryUntil()
    {
        if (! method_exists($this->mailable, 'retryUntil') && ! isset($this->mailable->retryUntil)) {
            return null;
        }

        return $this->mailable->retryUntil ?? $this->mailable->retryUntil();
    }

    /**
     * Call the failed method on the mailable instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->mailable, 'failed')) {
            $this->mailable->failed($e);
        }
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->mailable);
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }
}
