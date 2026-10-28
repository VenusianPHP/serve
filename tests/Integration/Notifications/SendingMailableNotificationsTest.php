<?php

namespace Venusian\Serve\Tests\Integration\Notifications;

use Voyager\Database\Instrument\Model;
use Mariner\Notifications\Messages\MailMessage;
use Mariner\Notifications\Notifiable;
use Mariner\Notifications\Notification;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('notified-users'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.driver', 'array');

    $app['config']->set('webapp.locale', 'en');

    $app['config']->set('mail.markdown.theme', 'blank');

    $app['view']->addLocation(__DIR__.'/Fixtures');
});

test('markdown notification', function () {
    $user = MailableNotificationUser::forceCreate([
        'email' => 'nuno@laravel.com',
    ]);

    $user->notify(new MarkdownNotification());

    $message = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
    $email = $message->toString();
    $textBody = $message->getTextBody();

    $cid = explode(' cid:', (new Stringable($textBody))->explode("\n")
        ->filter(fn ($line) => str_contains($line, 'Embed content: cid:'))
        ->first())[1];

    $filename = explode(' file: ', (new Stringable($textBody))->explode("\n")
        ->filter(fn ($line) => str_contains($line, 'Embed file: '))
        ->first())[1];

    expect($email)->toContain(<<<EOT
        Content-Type: application/x-php; name=$filename\r
        Content-Transfer-Encoding: base64\r
        Content-Disposition: inline; name=$filename;\r
         filename=$filename\r
        Content-ID: <$cid>\r
        EOT);
});

test('can set theme', function () {
    $user = MailableNotificationUser::forceCreate([
        'email' => 'nuno@laravel.com',
    ]);

    $user->notify(new MarkdownNotification('color-test'));
    $mailTransport = app('mailer')->getSymfonyTransport();

    $contents = $mailTransport->messages()[0]->getOriginalMessage()->toString();
    expect($contents)->toContain('<body style=3D"color: test;">');

    // confirm passing no theme resets to the app's default theme
    $user->notify(new MarkdownNotification());

    $contents = $mailTransport->messages()[1]->getOriginalMessage()->toString();
    expect($contents)->not->toContain('<body style=3D"color: test;">');
});

class MailableNotificationUser extends Model
{
    use Notifiable;

    public $table = 'users';
    public $timestamps = false;
}

class MarkdownNotification extends Notification
{
    public function __construct(
        protected $theme = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)->markdown('markdown');

        if (! is_null($this->theme)) {
            $message->theme($this->theme);
        }

        return $message;
    }
}
