<?php

namespace Venusian\Serve\Tests\Integration\Notifications;

use Mariner\Contracts\Mail\Factory as MailFactory;
use Mariner\Contracts\Mail\Mailer;
use Mariner\Mail\Events\MessageSending;
use Mariner\Mail\Mailable;
use Mariner\Mail\Mailables\Content;
use Mariner\Mail\Mailables\Envelope;
use Mariner\Mail\Message;
use Mariner\Notifications\Channels\MailChannel;
use Mariner\Notifications\Messages\MailMessage;
use Mariner\Notifications\Notification;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Str;
use Venusian\Serve\Tests\Notifications\Fixtures\Models\NotifiableUser;
use Mockery;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('notified-users'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.default', 'array');
    $app['config']->set('mail.mailers.array', ['transport' => 'array']);
    $app['config']->set('mail.mailers.foo', ['transport' => 'array']);

    $app['view']->addLocation(__DIR__.'/Fixtures');
});

test('mail is sent', function () {
    $notification = new TestMailNotification;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $sending = captureSendingData();

    $user->notify($notification);

    $message = sentMessage('foo');

    expect(addresses($message->getTo()))->toBe([['taylor@laravel.com', '']]);
    expect(addresses($message->getCc()))->toBe([['cc@deepblue.com', 'cc']]);
    expect(addresses($message->getBcc()))->toBe([['bcc@deepblue.com', 'bcc']]);
    expect(addresses($message->getFrom()))->toBe([['jack@deepblue.com', 'Jacques Mayol']]);
    expect(addresses($message->getReplyTo()))->toBe([['jack@deepblue.com', 'Jacques Mayol']]);
    expect($message->getSubject())->toBe('Test Mail Notification');
    expect($message->getPriority())->toBe(1);
    expect($message->getHtmlBody())->toContain('The introduction to the notification.');
    expect($message->getTextBody())->toContain('The introduction to the notification.');

    expect(Arr::only($sending->data, [
        '__mariner_notification_id', '__mariner_notification', '__mariner_notification_queued',
    ]))->toBe([
        '__mariner_notification_id' => $notification->id,
        '__mariner_notification' => TestMailNotification::class,
        '__mariner_notification_queued' => false,
    ]);
    expect(Arr::except($sending->data, [
        'message', 'mailer', '__mariner_notification_id', '__mariner_notification', '__mariner_notification_queued',
    ]))->toBe($notification->toMail($user)->toArray());
});

test('mail is sent with custom theme', function () {
    $notification = new TestMailNotificationWithCustomTheme;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage('foo');

    expect($message->getSubject())->toBe('Test Mail Notification With Custom Theme');
    expect($message->getHtmlBody())->toContain('#123456');
});

test('mail uses default theme when none is given', function () {
    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify(new TestMailNotificationWithSubject);

    expect(sentMessage()->getHtmlBody())->not->toContain('#123456');
});

test('mail is sent to named address', function () {
    $user = NotifiableUserWithNamedAddress::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    // A real Message cannot take this mixed named/unnamed address array
    // (see Message::addAddresses), so the address handoff is pinned here.
    expectMailerSend(
        fn ($view) => expect($view)->toBeArray(),
        function ($message) {
            $message->expects('to')->with(['taylor@laravel.com' => 'Taylor Otwell', 'foo_taylor@laravel.com']);
            $message->expects('subject')->with('mail custom subject');
        }
    );

    $user->notify(new TestMailNotificationWithSubject);
});

test('mail is sent with subject', function () {
    $notification = new TestMailNotificationWithSubject;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage();

    expect(addresses($message->getTo()))->toBe([['taylor@laravel.com', '']]);
    expect($message->getSubject())->toBe('mail custom subject');
});

test('mail is sent to multiple addresses', function () {
    $notification = new TestMailNotificationWithSubject;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUserWithMultipleAddresses::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage();

    expect(addresses($message->getTo()))->toBe([
        ['foo_taylor@laravel.com', ''],
        ['bar_taylor@laravel.com', ''],
    ]);
    expect($message->getSubject())->toBe('mail custom subject');
});

test('mail is sent using mailable', function () {
    $notification = new TestMailNotificationWithMailable;

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage();

    expect(addresses($message->getTo()))->toBe([['taylor@laravel.com', '']]);
    expect($message->getSubject())->toBe('Mailable Subject');
    expect($message->getHtmlBody())->toContain('mailable body');
});

test('mail is sent using mail message with html and plain', function () {
    $notification = new TestMailNotificationWithHtmlAndPlain;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage();

    expect(addresses($message->getTo()))->toBe([['taylor@laravel.com', '']]);
    expect($message->getSubject())->toBe('Test Mail Notification With Html And Plain');
    expect(trim($message->getHtmlBody()))->toBe('htmlContent');
    expect(trim($message->getTextBody()))->toBe('plainContent');
});

test('mail is sent using mail message with html only', function () {
    $notification = new TestMailNotificationWithHtmlOnly;
    $notification->id = Str::uuid()->toString();

    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    $user->notify($notification);

    $message = sentMessage();

    expect(addresses($message->getTo()))->toBe([['taylor@laravel.com', '']]);
    expect($message->getSubject())->toBe('Test Mail Notification With Html Only');
    expect(trim($message->getHtmlBody()))->toBe('htmlContent');
    expect($message->getTextBody())->toBeNull();
});

test('mail is sent using mail message with plain only', function () {
    $user = NotifiableUser::forceCreate([
        'email' => 'taylor@laravel.com',
    ]);

    // A real Mailer drops a [null, 'plain'] view (Mailer::parseView checks
    // isset($view[0])), so the view handoff is pinned here.
    expectMailerSend(
        fn ($view) => expect($view)->toBe([null, 'plain']),
        function ($message) {
            $message->expects('to')->with(['taylor@laravel.com']);
            $message->expects('subject')->with('Test Mail Notification With Plain Only');
        }
    );

    $user->notify(new TestMailNotificationWithPlainOnly);
});

function expectMailerSend(callable $viewAssertion, callable $messageExpectations): void
{
    $mailer = Mockery::mock(Mailer::class);
    $mailer->expects('send')->withArgs(function ($view, $data, $callback) use ($viewAssertion, $messageExpectations) {
        $viewAssertion($view);

        $message = Mockery::mock(Message::class);
        $messageExpectations($message);
        $callback($message);

        return true;
    });

    $factory = Mockery::mock(MailFactory::class);
    $factory->expects('mailer')->andReturn($mailer);

    app()->registerInstance(MailFactory::class, $factory);
}

function captureSendingData(): object
{
    $captured = new class
    {
        public $data = [];
    };

    app('signals')->listen(MessageSending::class, function (MessageSending $event) use ($captured) {
        $captured->data = $event->data;
    });

    return $captured;
}

function sentMessage(string $mailer = 'array'): Email
{
    $messages = app('mail.manager')->mailer($mailer)->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages->first()->getOriginalMessage();
}

function addresses(array $addresses): array
{
    return array_map(fn (Address $address) => [$address->getAddress(), $address->getName()], $addresses);
}

class NotifiableUserWithNamedAddress extends NotifiableUser
{
    public function routeNotificationForMail($notification)
    {
        return [
            $this->email => $this->name,
            'foo_'.$this->email,
        ];
    }
}

class NotifiableUserWithMultipleAddresses extends NotifiableUser
{
    public function routeNotificationForMail($notification)
    {
        return [
            'foo_'.$this->email,
            'bar_'.$this->email,
        ];
    }
}

class TestMailNotification extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->priority(1)
            ->cc('cc@deepblue.com', 'cc')
            ->bcc('bcc@deepblue.com', 'bcc')
            ->from('jack@deepblue.com', 'Jacques Mayol')
            ->replyTo('jack@deepblue.com', 'Jacques Mayol')
            ->line('The introduction to the notification.')
            ->mailer('foo');
    }
}

class TestMailNotificationWithSubject extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('mail custom subject')
            ->line('The introduction to the notification.');
    }
}

class TestMailNotificationWithMailable extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return new TestNotificationMailable;
    }
}

class TestNotificationMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(to: ['taylor@laravel.com'], subject: 'Mailable Subject');
    }

    public function content()
    {
        return new Content(htmlString: 'mailable body');
    }
}

class TestMailNotificationWithHtmlAndPlain extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->view(['html', 'plain']);
    }
}

class TestMailNotificationWithHtmlOnly extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->view('html');
    }
}

class TestMailNotificationWithPlainOnly extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->view([null, 'plain']);
    }
}

class TestMailNotificationWithCustomTheme extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->priority(1)
            ->cc('cc@deepblue.com', 'cc')
            ->bcc('bcc@deepblue.com', 'bcc')
            ->from('jack@deepblue.com', 'Jacques Mayol')
            ->replyTo('jack@deepblue.com', 'Jacques Mayol')
            ->line('The introduction to the notification.')
            ->theme('my-custom-theme')
            ->mailer('foo');
    }
}
