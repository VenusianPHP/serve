<?php

namespace Venusian\Serve\Tests\Mail;

use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Mail\MailManager;
use Mariner\Mail\Transport\ResendTransport;
use Mockery;
use Resend\Contracts\Client;
use Resend\Email as ResendEmail;
use Resend\Service\Email as EmailService;
use Symfony\Component\Mime\Email;

afterEach(function () {
    Mockery::close();
});

test('get transport', function () {
    $container = new ControlPanel;

    $container->registerSingleton('config', function () {
        return new Repository([
            'services.resend' => [
                'key' => 'foo',
            ],
        ]);
    });

    $manager = new MailManager($container);

    /** @var \Mariner\Mail\Transport\ResendTransport $transport */
    $transport = $manager->createSymfonyTransport(['transport' => 'resend', 'key' => 'foo']);

    expect((string) $transport)->toBe('resend');
});

test('send', function () {
    $email = new Email();
    $email->subject('Foo subject');
    $email->text('Bar body');
    $email->sender('myself@example.com');
    $email->to('me@example.com');

    $client = Mockery::mock(Client::class);
    $emailService = Mockery::mock(EmailService::class);
    $client->emails = $emailService;

    $emailService->expects('send')
        ->andReturn(ResendEmail::from([
            'id' => 'resend_id_test',
            'from' => 'myself@example.com',
            'to' => 'me@example.com',
            'created_at' => '2023-04-08T00:00:00.000Z',
        ]));

    $transport = new ResendTransport($client);
    $sentMessage = $transport->send($email);

    expect($sentMessage->getMessageId())->toBe('resend_id_test');
    expect($sentMessage->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString())->toBe('resend_id_test');
});
