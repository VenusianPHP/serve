<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use ReflectionProperty;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;

test('get failover transport with configured transports', function () {
    $this->app['config']->set('mail.default', 'failover');

    $this->app['config']->set('mail.mailers', [
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'sendmail',
                'array',
            ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],

        'array' => [
            'transport' => 'array',
        ],
    ]);

    $transport = app('mailer')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(FailoverTransport::class);
});

test('get failover transport with configured retry period', function () {
    $this->app['config']->set('mail.default', 'failover');

    $this->app['config']->set('mail.mailers', [
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'sendmail',
                'array',
            ],
            'retry_after' => 5,
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],

        'array' => [
            'transport' => 'array',
        ],
    ]);

    $transport = app('mailer')->getSymfonyTransport();
    expect((new ReflectionProperty(RoundRobinTransport::class, 'retryPeriod'))->getValue($transport))->toBe(5);
});

test('get failover transport with laravel6 style mail configuration', function () {
    $this->app['config']->set('mail.driver', 'failover');

    $this->app['config']->set('mail.mailers', [
        'sendmail',
        'array',
    ]);

    $this->app['config']->set('mail.sendmail', '/usr/sbin/sendmail -bs');

    $transport = app('mailer')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(FailoverTransport::class);
});
