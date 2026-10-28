<?php

namespace Venusian\Serve\Tests\Mail;

use Aws\Command;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\Ses\SesClient;
use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Mail\MailManager;
use Mariner\Mail\Transport\SesTransport;
use Mariner\View\Factory;
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

    /** @var \Mariner\Mail\Transport\SesTransport $transport */
    $transport = $manager->createSymfonyTransport(['transport' => 'ses']);

    $ses = $transport->ses();

    expect($ses->getRegion())->toBe('us-east-1');

    expect((string) $transport)->toBe('ses');
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
    $message->getHeaders()->addTextHeader('X-Ses-List-Management-Options', 'contactListName=TestList;topicName=TestTopic');

    $mock = new MockHandler;
    $mock->append(new Result(['MessageId' => 'ses-message-id']));

    $client = new SesClient([
        'credentials' => new Credentials('foo', 'bar'),
        'region' => 'us-east-1',
        'version' => 'latest',
        'handler' => $mock,
    ]);

    (new SesTransport($client))->send($message);

    $arg = $mock->getLastCommand()->toArray();

    expect($arg['Source'])->toBe('myself@example.com');
    expect($arg['Destinations'])->toBe(['me@example.com', 'you@example.com']);
    expect($arg['ListManagementOptions'])->toBe(['ContactListName' => 'TestList', 'TopicName' => 'TestTopic']);
    expect($arg['Tags'])->toBe([['Name' => 'FooTag', 'Value' => 'TagValue']]);
    expect($arg['RawMessage']['Data'])->toContain('Reply-To: Taylor Otwell <taylor@example.com>');
});

test('send error', function () {
    $message = new Email();
    $message->subject('Foo subject');
    $message->text('Bar body');
    $message->sender('myself@example.com');
    $message->to('me@example.com');

    $mock = new MockHandler;
    $mock->append(new AwsException('Email address is not verified.', new Command('sendRawEmail')));

    $client = new SesClient([
        'credentials' => new Credentials('foo', 'bar'),
        'region' => 'us-east-1',
        'version' => 'latest',
        'handler' => $mock,
    ]);

    (new SesTransport($client))->send($message);
})->throws(TransportException::class);

test('ses local configuration', function () {
    $container = new ControlPanel;

    $container->registerSingleton('config', function () {
        return new Repository([
            'mail' => [
                'mailers' => [
                    'ses' => [
                        'transport' => 'ses',
                        'region' => 'eu-west-1',
                        'options' => [
                            'ConfigurationSetName' => 'Laravel',
                            'Tags' => [
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

    /** @var \Mariner\Mail\Transport\SesTransport $transport */
    $transport = $mailer->getSymfonyTransport();

    expect($transport->ses()->getRegion())->toBe('eu-west-1');

    expect($transport->getOptions())->toBe([
        'ConfigurationSetName' => 'Laravel',
        'Tags' => [
            ['Name' => 'Laravel', 'Value' => 'Framework'],
        ],
    ]);
});
