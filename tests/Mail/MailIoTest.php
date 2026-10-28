<?php

use Mariner\Mail\Mailable;
use Mariner\Mail\SentMessage;
use Mariner\Notifications\ChannelManager;
use Mariner\Notifications\Messages\MailMessage;
use Mariner\Notifications\Notification;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\IOPools\Promise;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->mailbox = sys_get_temp_dir().'/mariner-mailbox-'.bin2hex(random_bytes(4));
    mkdir($this->mailbox);

    // Runs in whichever process sends: this one, or a worker.
    $this->app['config']->set('mail.mailers.box', [
        'transport' => 'sendmail',
        'path' => escapeshellarg(dirname(__DIR__).'/Fixtures/sendmail.sh').' '.escapeshellarg($this->mailbox).' -t',
    ]);
    $this->app['config']->set('mail.default', 'box');
    $this->app['config']->set('mail.from', ['address' => 'ada@example.test', 'name' => 'Ada']);
    $this->mail = $this->app['mail.manager'];
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->mailbox);
    WebApp::tearDown($this);
});

/** The messages the fake sendmail received, one file each. */
function mailbox(string $dir): array
{
    return array_map('file_get_contents', glob($dir.'/message.*'));
}

// Thread workers need ext-parallel; without it, null picks process workers.
dataset('pools', array_merge([null, 'process'], extension_loaded('parallel') ? ['thread'] : []));

it('keeps blocking sends exactly as they were', function () {
    $sent = $this->mail->mailer()->raw('plain body', fn ($m) => $m->to('grace@example.test')->subject('Engines'));

    expect($sent)->toBeInstanceOf(SentMessage::class)
        ->and(mailbox($this->mailbox))->toHaveCount(1)
        ->and(mailbox($this->mailbox)[0])->toContain('Subject: Engines');
});

it('sends through a worker from a loop task, answering with the sent message', function (?string $pool) {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'from a stream');
    rewind($stream);

    $promise = $this->mail->mailer()->via($pool)->send(['raw' => 'plain body'], [], function ($m) use ($stream) {
        $m->to('grace@example.test')->subject('Engines')->attachData($stream, 'stream.txt', ['mime' => 'text/plain']);
    });

    $sent = $this->app['event-loop']->await($promise);

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($sent)->toBeInstanceOf(SentMessage::class)
        ->and($sent->getEnvelope()->getRecipients()[0]->getAddress())->toBe('grace@example.test')
        ->and($box = mailbox($this->mailbox))->toHaveCount(1)
        ->and($box[0])->toContain('Subject: Engines')->toContain('stream.txt')->toContain(base64_encode('from a stream'));
})->with('pools');

it('fires the sending and sent events in this process', function () {
    $events = [];
    $this->app['signals']->listen(Mariner\Mail\Events\MessageSending::class, function () use (&$events) {
        $events[] = 'sending';
    });
    $this->app['signals']->listen(Mariner\Mail\Events\MessageSent::class, function () use (&$events) {
        $events[] = 'sent';
    });

    $this->app['event-loop']->await($this->mail->mailer()->via()->send(['raw' => 'x'], [], fn ($m) => $m->to('grace@example.test')));

    expect($events)->toBe(['sending', 'sent']);
});

it('sends a mailable through a worker', function () {
    $mailable = new class extends Mailable {
        public function build()
        {
            return $this->to('grace@example.test')->subject('Compilers')->html('<p>hi</p>');
        }
    };

    $this->app['event-loop']->await($this->mail->mailer()->via()->send($mailable));

    expect(mailbox($this->mailbox)[0])->toContain('Subject: Compilers');
});

it('lets two loop tasks overlap their sends', function () {
    $loop = $this->app['event-loop'];
    $events = [];
    $send = function (string $to) use ($loop, &$events) {
        $async = $this->mail->mailer()->via();

        return $loop->async(function () use ($async, $to, $loop, &$events) {
            $events[] = "{$to}:start";
            $loop->await($async->send(['raw' => 'x'], [], fn ($m) => $m->to("{$to}@example.test")->subject($to)));
            $events[] = "{$to}:done";
        });
    };

    $a = $send('ada');
    $b = $send('grace');
    $loop->await($a);
    $loop->await($b);

    expect(array_search('grace:start', $events))->toBeLessThan(array_search('ada:done', $events))
        ->and(mailbox($this->mailbox))->toHaveCount(2);
});

it('refuses mailers a worker cannot rebuild', function () {
    $this->app['config']->set('mail.mailers.memory', ['transport' => 'array']);

    expect(fn () => $this->mail->mailer('memory')->via())->toThrow(LogicException::class, 'array transport')
        ->and(fn () => (new Mariner\Mail\Mailer('handmade', $this->app['view'], new Symfony\Component\Mailer\Transport\NullTransport))->via())
        ->toThrow(LogicException::class, 'not built from config');
});

it('sends notification mail through a worker under ChannelManager::async()', function () {
    $notification = new class extends Notification {
        public function via($notifiable)
        {
            return ['mail'];
        }

        public function toMail($notifiable)
        {
            return (new MailMessage)->subject('Welcome')->line('hello');
        }
    };

    $notifiable = (new Mariner\Notifications\AnonymousNotifiable)->route('mail', 'grace@example.test');

    $this->app['event-loop']->await($this->app[ChannelManager::class]->async()->sendNow($notifiable, $notification));

    expect(mailbox($this->mailbox)[0])->toContain('Subject: Welcome');
});
