<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use InvalidArgumentException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

test('empty transport config', function ($transport) {
    $this->app['config']->set('mail.mailers.custom_smtp', [
        'transport' => $transport,
        'host' => null,
        'port' => null,
        'encryption' => null,
        'username' => null,
        'password' => null,
        'timeout' => null,
    ]);

    expect(fn () => $this->app['mail.manager']->mailer('custom_smtp'))
        ->toThrow(InvalidArgumentException::class, "Unsupported mail transport [{$transport}]");
})->with('emptyTransportConfigDataProvider');

test('mail url config', function ($scheme, $port) {
    $this->app['config']->set('mail.mailers.smtp_url', [
        'scheme' => $scheme,
        'url' => "smtp://usr:pwd@127.0.0.2:{$port}",
    ]);

    $mailer = $this->app['mail.manager']->mailer('smtp_url');
    $transport = $mailer->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    expect($transport->getUsername())->toBe('usr');
    expect($transport->getPassword())->toBe('pwd');
    expect($transport->getStream()->getHost())->toBe('127.0.0.2');
    expect($transport->getStream()->getPort())->toBe($port);
    expect($transport->getStream()->isTLS())->toBe($port === 465);
    expect($transport->isAutoTls())->toBeTrue();
})->with([
    [null, 5876],
    [null, 465],
    ['smtp', 25],
    ['smtp', 2525],
    ['smtps', 465],
    ['smtp', 465],
]);

test('mail url config with auto tls', function ($scheme, $port) {
    $this->app['config']->set('mail.mailers.smtp_url', [
        'scheme' => $scheme,
        'url' => "smtp://usr:pwd@127.0.0.2:{$port}?auto_tls=true",
    ]);

    $mailer = $this->app['mail.manager']->mailer('smtp_url');
    $transport = $mailer->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    expect($transport->getUsername())->toBe('usr');
    expect($transport->getPassword())->toBe('pwd');
    expect($transport->getStream()->getHost())->toBe('127.0.0.2');
    expect($transport->getStream()->getPort())->toBe($port);
    expect($transport->getStream()->isTLS())->toBe($port === 465);
    expect($transport->isAutoTls())->toBeTrue();
})->with([
    [null, 5876],
    [null, 465],
    ['smtp', 25],
    ['smtp', 2525],
    ['smtps', 465],
    ['smtp', 465],
]);

test('mail url config with auto tls disabled', function ($scheme, $port) {
    $this->app['config']->set('mail.mailers.smtp_url', [
        'scheme' => $scheme,
        'url' => "smtp://usr:pwd@127.0.0.2:{$port}?auto_tls=false",
    ]);

    $mailer = $this->app['mail.manager']->mailer('smtp_url');
    $transport = $mailer->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    expect($transport->getUsername())->toBe('usr');
    expect($transport->getPassword())->toBe('pwd');
    expect($transport->getStream()->getHost())->toBe('127.0.0.2');
    expect($transport->getStream()->getPort())->toBe($port);
    expect($transport->isAutoTls())->toBeFalse();
    expect($transport->getStream()->isTLS())->toBe($port === 465 && $scheme !== 'smtp');
})->with([
    [null, 5876],
    [null, 465],
    ['smtp', 25],
    ['smtp', 2525],
    ['smtps', 465],
    ['smtp', 465],
]);

test('build', function () {
    $config = [
        'transport' => 'smtp',
        'host' => '127.0.0.2',
        'port' => 5876,
        'encryption' => 'tls',
        'username' => 'usr',
        'password' => 'pwd',
        'timeout' => 5,
    ];

    $mailer = $this->app['mail.manager']->build($config);
    $transport = $mailer->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    expect($transport->getUsername())->toBe('usr');
    expect($transport->getPassword())->toBe('pwd');
    expect($transport->getStream()->getHost())->toBe('127.0.0.2');
    expect($transport->getStream()->getPort())->toBe(5876);
});

test('mail manager can resolve backed enum mailer', function () {
    $this->app['config']->set('mail.mailers.array', [
        'transport' => 'array',
    ]);

    $mailer1 = $this->app['mail.manager']->mailer(MailerName::ArrayMailer);
    $mailer2 = $this->app['mail.manager']->mailer('array');

    expect($mailer2)->toBe($mailer1);
});

test('mail manager can resolve backed enum driver', function () {
    $this->app['config']->set('mail.mailers.array', [
        'transport' => 'array',
    ]);

    $mailer1 = $this->app['mail.manager']->driver(MailerName::ArrayMailer);
    $mailer2 = $this->app['mail.manager']->driver('array');

    expect($mailer2)->toBe($mailer1);
});

test('set default driver accepts backed enum', function () {
    $this->app['config']->set('mail.mailers.array', [
        'transport' => 'array',
    ]);

    $this->app['mail.manager']->setDefaultDriver(MailerName::ArrayMailer);

    expect($this->app['config']->get('mail.default'))->toBe('array');
});

test('purge accepts backed enum', function () {
    $this->app['config']->set('mail.mailers.array', [
        'transport' => 'array',
    ]);

    $manager = $this->app['mail.manager'];

    $mailer1 = $manager->mailer(MailerName::ArrayMailer);
    $manager->purge(MailerName::ArrayMailer);
    $mailer2 = $manager->mailer(MailerName::ArrayMailer);

    expect($mailer2)->not->toBe($mailer1);
});

test('global to address without name', function () {
    $this->app['config']->set('mail.mailers.array', ['transport' => 'array']);
    $this->app['config']->set('mail.to', ['address' => 'taylor@laravel.com']);
    $mailer = $this->app['mail.manager']->mailer('array');

    $sentMessage = $mailer->raw('Hello World', function ($message) {
        $message->to('jack@laravel.com');
    });

    expect($sentMessage->toString())->toContain('To: taylor@laravel.com');
});

dataset('emptyTransportConfigDataProvider', function () {
    return [
        [null],
        [''],
        [' '],
    ];
});

enum MailerName: string
{
    case ArrayMailer = 'array';
}
