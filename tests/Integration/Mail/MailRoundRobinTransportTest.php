<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Symfony\Component\Mailer\Transport\RoundRobinTransport;

test('get round robin transport with configured transports', function () {
    $this->app['config']->set('mail.default', 'roundrobin');

    $this->app['config']->set('mail.mailers', [
        'roundrobin' => [
            'transport' => 'roundrobin',
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
    expect($transport)->toBeInstanceOf(RoundRobinTransport::class);
});

test('get round robin transport with laravel6 style mail configuration', function () {
    $this->app['config']->set('mail.driver', 'roundrobin');

    $this->app['config']->set('mail.mailers', [
        'sendmail',
        'array',
    ]);

    $this->app['config']->set('mail.sendmail', '/usr/sbin/sendmail -bs');

    $transport = app('mailer')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(RoundRobinTransport::class);
});
