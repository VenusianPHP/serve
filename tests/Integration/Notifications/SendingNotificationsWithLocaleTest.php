<?php

namespace Venusian\Serve\Tests\Integration\Notifications;

use Mariner\Contracts\Translation\HasLocalePreference;
use Voyager\Database\Instrument\Model;
use Mariner\Core\Events\LocaleUpdated;
use Mariner\Notifications\Channels\MailChannel;
use Mariner\Notifications\Messages\MailMessage;
use Mariner\Notifications\Notifiable;
use Mariner\Notifications\Notification;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Notification as NotificationFacade;
use Mariner\Testing\Assert;
use Venusian\Serve\Tests\Mail\Fixtures\GreetingMailable;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('notified-users'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.driver', 'array');

    $app['config']->set('webapp.locale', 'en');

    $app['view']->addLocation(__DIR__.'/Fixtures');

    $app['translator']->setLoaded([
        '*' => [
            '*' => [
                'en' => ['hi' => 'hello'],
                'es' => ['hi' => 'hola'],
                'fr' => ['hi' => 'bonjour'],
            ],
        ],
    ]);
});

test('mail is sent with default locale', function () {
    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    NotificationFacade::send($user, new GreetingMailNotification);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('hello');
});

test('mail is sent with facade selected locale', function () {
    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    NotificationFacade::locale('fr')->send($user, new GreetingMailNotification);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
});

test('mail is sent with facade selected locale after another notification was sent', function () {
    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    NotificationFacade::send($user, new GreetingMailNotification);
    NotificationFacade::locale('fr')->send($user, new GreetingMailNotification);

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages[0]->toString())->toContain('hello');
    expect($messages[1]->toString())->toContain('bonjour');
});

test('facade selected locale is not used for later notifications', function () {
    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    NotificationFacade::locale('fr')->send($user, new GreetingMailNotification);
    NotificationFacade::send($user, new GreetingMailNotification);
    $user->notify(new GreetingMailNotification);

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages[0]->toString())->toContain('bonjour');
    expect($messages[1]->toString())->not->toContain('bonjour');
    expect($messages[2]->toString())->not->toContain('bonjour');
});

test('mail is sent with notification selected locale', function () {
    $users = [
        NotifiableLocalizedUser::forceCreate([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]),
        NotifiableLocalizedUser::forceCreate([
            'email' => 'mohamed@laravel.com',
            'name' => 'Mohamed Said',
        ]),
    ];

    NotificationFacade::send($users, (new GreetingMailNotification)->locale('fr'));

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');

    expect(app('mailer')->getSymfonyTransport()->messages()[1]->toString())->toContain('bonjour');
});

test('mailable is sent with selected locale', function () {
    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    NotificationFacade::locale('fr')->send($user, new GreetingMailNotificationWithMailable);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
});

test('locale is sent with notifiable preferred locale', function () {
    $recipient = new NotifiableEmailLocalePreferredUser([
        'email' => 'test@mail.com',
        'email_locale' => 'fr',
    ]);

    $recipient->notify(new GreetingMailNotification);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
});

test('locale is sent with notifiable preferred locale for multiple recipients', function () {
    $recipients = [
        new NotifiableEmailLocalePreferredUser([
            'email' => 'test@mail.com',
            'email_locale' => 'fr',
        ]),
        new NotifiableEmailLocalePreferredUser([
            'email' => 'test.2@mail.com',
            'email_locale' => 'es',
        ]),
        NotifiableLocalizedUser::forceCreate([
            'email' => 'test.3@mail.com',
        ]),
    ];

    NotificationFacade::send(
        $recipients, new GreetingMailNotification
    );

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
    expect(app('mailer')->getSymfonyTransport()->messages()[1]->toString())->toContain('hola');
    expect(app('mailer')->getSymfonyTransport()->messages()[2]->toString())->toContain('hello');
});

test('locale is sent with notification selected locale overriding notifiable preferred locale', function () {
    $recipient = new NotifiableEmailLocalePreferredUser([
        'email' => 'test@mail.com',
        'email_locale' => 'es',
    ]);

    $recipient->notify(
        (new GreetingMailNotification)->locale('fr')
    );

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
});

test('locale is sent with facade selected locale overriding notifiable preferred locale', function () {
    $recipient = new NotifiableEmailLocalePreferredUser([
        'email' => 'test@mail.com',
        'email_locale' => 'es',
    ]);

    NotificationFacade::locale('fr')->send(
        $recipient, new GreetingMailNotification
    );

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');
});

test('mail is sent with locale updated listeners called', function () {
    Carbon::setTestNow('2018-07-25');

    app('signals')->listen(LocaleUpdated::class, function ($event) {
        Carbon::setLocale($event->locale);
    });

    $user = NotifiableLocalizedUser::forceCreate([
        'email' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    $user->notify((new GreetingMailNotification)->locale('fr'));

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('bonjour');

    Assert::assertMatchesRegularExpression('/dans (1|un) jour/',
        app('mailer')->getSymfonyTransport()->messages()[0]->toString()
    );

    expect($this->app->isLocale('en'))->toBeTrue();

    expect(Carbon::getLocale())->toBe('en');
})->todo('pending framework: LocaleUpdated event / app locale');

class NotifiableLocalizedUser extends Model
{
    use Notifiable;

    public $table = 'users';
    public $timestamps = false;
}

class NotifiableEmailLocalePreferredUser extends Model implements HasLocalePreference
{
    use Notifiable;

    protected $fillable = [
        'email',
        'email_locale',
    ];

    public function preferredLocale()
    {
        return $this->email_locale;
    }
}

class GreetingMailNotification extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->greeting(__('hi'))
            ->line(Carbon::tomorrow()->diffForHumans());
    }
}

class GreetingMailNotificationWithMailable extends Notification
{
    public function via($notifiable)
    {
        return [MailChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new GreetingMailable)
            ->to($notifiable->email);
    }
}
