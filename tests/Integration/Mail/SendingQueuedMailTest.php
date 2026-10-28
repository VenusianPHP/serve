<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Mailable;
use Mariner\Mail\SendQueuedMailable;
use Voyager\Queue\Middleware\RateLimited;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Mail;
use Venusian\Serve\Tests\Fixtures\QueueRecorder;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.driver', 'array');

    $app['view']->addLocation(__DIR__.'/Fixtures');
});

test('mail is sent with default locale', function () {
    $queue = new QueueRecorder;
    $this->instance('queue', $queue);

    Mail::to('test@mail.com')->queue(new SendingQueuedMailTestMail);

    $queue->assertPushed(SendQueuedMailable::class, function ($job) {
        return $job->middleware[0] instanceof RateLimited;
    });
});

test('mail is sent with delay', function () {
    $queue = new QueueRecorder;
    $this->instance('queue', $queue);

    $delay = Carbon::now()->addMinutes(10);

    Mail::to('test@mail.com')->later($delay, new SendingQueuedMailTestMail);

    $queue->assertPushed(SendQueuedMailable::class, function ($job) use ($delay) {
        return $job->delay === $delay;
    });
});

class SendingQueuedMailTestMail extends Mailable
{
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('view');
    }

    public function middleware()
    {
        return [new RateLimited('limiter')];
    }
}

class SendingQueuedForwardedMailTestMail extends Mailable
{
    public $queue = 'mail-queue';

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('view');
    }
}
