<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Contracts\Translation\HasLocalePreference;
use Voyager\Database\Instrument\Model;
use Mariner\Core\Events\LocaleUpdated;
use Mariner\Mail\Mailable;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Mail;
use Mariner\Testing\Assert;
use Venusian\Serve\Tests\Mail\Fixtures\TestMail;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.driver', 'array');

    $app['config']->set('webapp.locale', 'en');

    $app['view']->addLocation(__DIR__.'/Fixtures');

    $app['translator']->setLoaded([
        '*' => [
            '*' => [
                'en' => ['nom' => 'name'],
                'ar' => ['nom' => 'esm'],
                'es' => ['nom' => 'nombre'],
            ],
        ],
    ]);
});

test('mail is sent with default locale', function () {
    Mail::to('test@mail.com')->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('name');
});

test('mail is sent with selected locale', function () {
    Mail::to('test@mail.com')->locale('ar')->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');
});

test('mail is sent with locale from mailable', function () {
    $mailable = new TestMail;
    $mailable->locale('ar');

    Mail::to('test@mail.com')->send($mailable);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');
});

test('locale is sent with model preferred locale', function () {
    $recipient = new TestEmailLocaleUser([
        'email' => 'test@mail.com',
        'email_locale' => 'ar',
    ]);

    Mail::to($recipient)->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');

    $mailable = new Mailable;
    $mailable->to($recipient);

    expect($mailable->locale)->toBe($recipient->email_locale);
});

test('locale is sent with selected locale overriding model preferred locale', function () {
    $recipient = new TestEmailLocaleUser([
        'email' => 'test@mail.com',
        'email_locale' => 'en',
    ]);

    Mail::to($recipient)->locale('ar')->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');
});

test('locale is sent with model preferred locale will ignore preferred locale of the cc recipient', function () {
    $toRecipient = new TestEmailLocaleUser([
        'email' => 'test@mail.com',
        'email_locale' => 'ar',
    ]);

    $ccRecipient = new TestEmailLocaleUser([
        'email' => 'test.cc@mail.com',
        'email_locale' => 'en',
    ]);

    Mail::to($toRecipient)->cc($ccRecipient)->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');
});

test('locale is not sent with model preferred locale when there are multiple recipients', function () {
    $recipients = [
        new TestEmailLocaleUser([
            'email' => 'test@mail.com',
            'email_locale' => 'ar',
        ]),
        new TestEmailLocaleUser([
            'email' => 'test.2@mail.com',
            'email_locale' => 'ar',
        ]),
    ];

    Mail::to($recipients)->send(new TestMail);

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('name');
});

test('locale is set back to default after mail sent', function () {
    Mail::to('test@mail.com')->locale('ar')->send(new TestMail);
    Mail::to('test@mail.com')->send(new TestMail);

    expect(app('translator')->getLocale())->toBe('en');

    expect(app('mailer')->getSymfonyTransport()->messages()[0]->toString())->toContain('esm');

    expect(app('mailer')->getSymfonyTransport()->messages()[1]->toString())->toContain('name');
});

test('mail is sent with locale updated listeners called', function () {
    Carbon::setTestNow('2018-04-01');

    app('signals')->listen(LocaleUpdated::class, function ($event) {
        Carbon::setLocale($event->locale);
    });

    Mail::to('test@mail.com')->locale('es')->send(new TimestampTestMail);

    Assert::assertMatchesRegularExpression('/nombre (en|dentro de) (un|1) d=C3=ADa/',
        app('mailer')->getSymfonyTransport()->messages()[0]->toString()
    );

    expect(Carbon::getLocale())->toBe('en');
})->todo('pending framework: LocaleUpdated event / app locale');

class TestEmailLocaleUser extends Model implements HasLocalePreference
{
    protected $fillable = [
        'email',
        'email_locale',
    ];

    public function preferredLocale()
    {
        return $this->email_locale;
    }
}

class TimestampTestMail extends Mailable
{
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('timestamp');
    }
}
