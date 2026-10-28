<?php

namespace Mariner\Notifications;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldBeEncrypted;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldQueueAfterCommit;
use Voyager\Database\Instrument\Collection as InstrumentCollection;
use Voyager\Database\Instrument\Model;
use Voyager\Queue\Attributes\Backoff;
use Voyager\Queue\Attributes\DeleteWhenMissingModels;
use Voyager\Queue\Attributes\FailOnTimeout;
use Voyager\Queue\Attributes\MaxExceptions;
use Voyager\Queue\Attributes\ReadsQueueAttributes;
use Voyager\Queue\Attributes\Timeout;
use Voyager\Queue\Attributes\Tries;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\SerializesModels;
use Voyager\NutsAndBolts\Collection;

class SendQueuedNotifications implements ShouldQueue
{
    use InteractsWithQueue, Queueable, ReadsQueueAttributes, SerializesModels;

    /**
     * The notifiable entities that should receive the notification.
     *
     * @var \Voyager\NutsAndBolts\Collection
     */
    public $notifiables;

    /**
     * The notification to be sent.
     *
     * @var \Mariner\Notifications\Notification
     */
    public $notification;

    /**
     * All of the channels to send the notification to.
     *
     * @var array
     */
    public $channels;

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
     * @var int
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
     * @param  mixed  $notifiables
     * @param  \Mariner\Notifications\Notification  $notification
     * @param  array|null  $channels
     */
    public function __construct($notifiables, $notification, ?array $channels = null)
    {
        $this->channels = $channels;
        $this->notification = $notification;
        $this->notifiables = $this->wrapNotifiables($notifiables);
        $this->tries = $this->getAttributeValue($notification, Tries::class, 'tries');
        $this->timeout = $this->getAttributeValue($notification, Timeout::class, 'timeout');
        $this->maxExceptions = $this->getAttributeValue($notification, MaxExceptions::class, 'maxExceptions');
        $this->deleteWhenMissingModels = $this->getAttributeValue($notification, DeleteWhenMissingModels::class, 'deleteWhenMissingModels') ?? false;
        $this->failOnTimeout = $this->getAttributeValue($notification, FailOnTimeout::class, 'failOnTimeout') ?? false;

        if ($notification instanceof ShouldQueueAfterCommit) {
            $this->afterCommit = true;
        } else {
            $this->afterCommit = property_exists($notification, 'afterCommit') ? $notification->afterCommit : null;
        }

        $this->shouldBeEncrypted = $notification instanceof ShouldBeEncrypted;
    }

    /**
     * Wrap the notifiable(s) in a collection.
     *
     * @param  mixed  $notifiables
     * @return \Voyager\NutsAndBolts\Collection
     */
    protected function wrapNotifiables($notifiables)
    {
        if ($notifiables instanceof Collection) {
            return $notifiables;
        } elseif ($notifiables instanceof Model) {
            return InstrumentCollection::wrap($notifiables);
        }

        return Collection::wrap($notifiables);
    }

    /**
     * Send the notifications.
     *
     * @return null
     */
    public function handle(): mixed
    {
        app(ChannelManager::class)->sendNow($this->notifiables, $this->notification, $this->channels);

        return null;
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->notification);
    }

    /**
     * Call the failed method on the notification instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->notification, 'failed')) {
            $this->notification->failed($e);
        }
    }

    /**
     * Get the number of seconds before a released notification will be available.
     *
     * @return mixed
     */
    public function backoff()
    {
        $backoff = $this->getAttributeValue($this->notification, Backoff::class, 'backoff');

        if (method_exists($this->notification, 'backoff')) {
            $backoff = $this->notification->backoff();
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
        if (! method_exists($this->notification, 'retryUntil') && ! isset($this->notification->retryUntil)) {
            return null;
        }

        return $this->notification->retryUntil ?? $this->notification->retryUntil();
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->notifiables = clone $this->notifiables;
        $this->notification = clone $this->notification;
    }
}
