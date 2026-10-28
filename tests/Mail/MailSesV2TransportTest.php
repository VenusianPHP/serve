<?php

namespace Venusian\Serve\Tests\Mail;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Mail\MailManager;
use Mariner\Mail\Transport\SesV2Transport;
use Mariner\View\Factory;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

test('get transport', function () {
    $container = new ControlPanel;

    $container->registerSingleton('config', function () {
        return new Repository([
            'services.ses' => [
                'key' => 'foo',
                'secret' => 'bar',
                'region' => 'us-east-1',
            ],
        ]);
    });

    $manager = new MailManager($container);

    /** @var \Mariner\Mail\Transport\SesV2Transport $transport */
    $transport = $manager->createSymfonyTransport(['transport' => 'ses-v2']);

    $ses = $transport->ses();

    expect($ses->getRegion())->toBe('us-east-1');

    expect((string) $transport)->toBe('ses-v2');
});

test('send', function () {
    $message = new Email();
    $message->subject('Foo subject');
    $message->text('Bar body');
    $message->sender('myself@example.com');
    $message->to('me@example.com');
    $message->bcc('you@example.com');
    $message->replyTo(new Address('taylor@example.com', 'Taylor Otwell'));
    $message->getHeaders()->add(new MetadataHeader('FooTag', 'TagValue'));
    $message->getHeaders()->addTextHeader('X-SES-LIST-MANAGEMENT-OPTIONS', 'contactListName=TestList;topicName=TestTopic');

    $client = Mockery::mock(SesV2Client::class);
    $sesResult = new Result(['MessageId' => 'ses-message-id']);
    $client->expects('sendEmail')
        ->with(Mockery::on(function ($arg) {
            return $arg['Source'] === 'myself@example.com' &&
                $arg['Destination']['ToAddresses'] === ['me@example.com', 'you@example.com'] &&
                $arg['ListManagementOptions'] === ['ContactListName' => 'TestList', 'TopicName' => 'TestTopic'] &&
                $arg['EmailTags'] === [['Name' => 'FooTag', 'Value' => 'TagValue']] &&
                str_contains($arg['Content']['Raw']['Data'], 'Reply-To: Taylor Otwell <taylor@example.com>');
        }))
        ->andReturn($sesResult);

    (new SesV2Transport($client))->send($message);
});

test('send with tenant name', function () {
    $message = new Email();
    $message->subject('Foo subject');
    $message->text('Bar body');
    $message->sender('myself@example.com');
    $message->to('me@example.com');
    $message->getHeaders()->addTextHeader('X-SES-TENANT-NAME', 'my-tenant');

    $client = Mockery::mock(SesV2Client::class);
    $sesResult = new Result(['MessageId' => 'ses-message-id']);
    $client->expects('sendEmail')
        ->with(Mockery::on(function ($arg) {
            return $arg['TenantName'] === 'my-tenant';
        }))
        ->andReturn($sesResult);

    (new SesV2Transport($client))->send($message);
});

test('send without tenant name does not set the option', function () {
    $message = new Email();
    $message->subject('Foo subject');
    $message->text('Bar body');
    $message->sender('myself@example.com');
    $message->to('me@example.com');

    $client = Mockery::mock(SesV2Client::class);
    $sesResult = new Result(['MessageId' => 'ses-message-id']);
    $client->expects('sendEmail')
        ->with(Mockery::on(function ($arg) {
            return ! array_key_exists('TenantName', $arg);
        }))
        ->andReturn($sesResult);

    (new SesV2Transport($client))->send($message);
});

test('send error', function () {
    $message = new Email();
    $message->subject('Foo subject');
    $message->text('Bar body');
    $message->sender('myself@example.com');
    $message->to('me@example.com');

    $client = Mockery::mock(SesV2Client::class);
    $client->expects('sendEmail')
        ->andThrow(new AwsException('Email address is not verified.', new Command('sendRawEmail')));

    (new SesV2Transport($client))->send($message);
})->throws(TransportException::class);

test('ses v2 local configuration', function () {
    $container = new ControlPanel;

    $container->registerSingleton('config', function () {
        return new Repository([
            'mail' => [
                'mailers' => [
                    'ses' => [
                        'transport' => 'ses-v2',
                        'region' => 'eu-west-1',
                        'options' => [
                            'ConfigurationSetName' => 'Laravel',
                            'EmailTags' => [
                                ['Name' => 'Laravel', 'Value' => 'Framework'],
                            ],
                        ],
                    ],
                ],
            ],
            'services' => [
                'ses' => [
                    'region' => 'us-east-1',
                ],
            ],
        ]);
    });

    $container->registerInstance('view', $this->createMock(Factory::class));

    $container->bind('signals', function () {
        return null;
    });

    $manager = new MailManager($container);

    /** @var \Mariner\Mail\Mailer $mailer */
    $mailer = $manager->mailer('ses');

    /** @var \Mariner\Mail\Transport\SesV2Transport $transport */
    $transport = $mailer->getSymfonyTransport();

    expect($transport->ses()->getRegion())->toBe('eu-west-1');

    expect($transport->getOptions())->toBe([
        'ConfigurationSetName' => 'Laravel',
        'EmailTags' => [
            ['Name' => 'Laravel', 'Value' => 'Framework'],
        ],
    ]);
});
