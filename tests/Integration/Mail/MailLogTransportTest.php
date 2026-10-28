<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Attachment;
use Mariner\Mail\Message;
use Mariner\Mail\Transport\LogTransport;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\Email;

test('get log transport with configured channel', function () {
    $this->app['config']->set('mail.driver', 'log');

    $this->app['config']->set('mail.log_channel', 'mail');

    $this->app['config']->set('logging.channels.mail', [
        'driver' => 'single',
        'path' => 'mail.log',
    ]);

    $transport = app('mailer')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(LogTransport::class);

    $logger = $transport->logger();
    expect($logger)->toBeInstanceOf(LoggerInterface::class);

    expect($monolog = $logger->getLogger())->toBeInstanceOf(Logger::class);
    expect($handlers = $monolog->getHandlers())->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(StreamHandler::class);
});

test('it decodes the message before logging', function () {
    $message = (new Message(new Email))
        ->from('noreply@example.com', 'no-reply')
        ->to('taylor@example.com', 'Taylor')
        ->html(<<<'BODY'
            Hi,

            <a href="https://example.com/reset-password=5e113c71a4c210aff04b3fa66f1b1299">Click here to reset your password</a>.

            All the best,

            Burt & Irving
            BODY)
        ->text('A text part');

    $actualLoggedValue = getLoggedEmailMessage($message);

    expect($actualLoggedValue)->not->toContain("=\r\n");
    expect($actualLoggedValue)->toContain('href=');
    expect($actualLoggedValue)->toContain('Burt & Irving');
    expect($actualLoggedValue)->toContain('https://example.com/reset-password=5e113c71a4c210aff04b3fa66f1b1299');
});

test('it only decodes quoted printable parts of the message before logging', function () {
    $message = (new Message(new Email))
        ->from('noreply@example.com', 'no-reply')
        ->to('taylor@example.com', 'Taylor')
        ->html(<<<'BODY'
            Hi,

            <a href="https://example.com/reset-password=5e113c71a4c210aff04b3fa66f1b1299">Click here to reset your password</a>.

            All the best,

            Burt & Irving
            BODY)
        ->text('A text part')
        ->attach(Attachment::fromData(fn () => 'My attachment', 'attachment.txt'));

    $actualLoggedValue = getLoggedEmailMessage($message);

    expect($actualLoggedValue)->toContain('href=');
    expect($actualLoggedValue)->toContain('Burt & Irving');
    expect($actualLoggedValue)->toContain('https://example.com/reset-password=5e113c71a4c210aff04b3fa66f1b1299');
    expect($actualLoggedValue)->toContain('name=attachment.txt');
    expect($actualLoggedValue)->toContain('filename=attachment.txt');
});

test('get log transport with psr logger', function () {
    $this->app['config']->set('mail.driver', 'log');

    $this->app->registerInstance('log', $logger = new NullLogger);

    $transportLogger = app('mailer')->getSymfonyTransport()->logger();

    expect($transportLogger)->toEqual($logger);
});

function getLoggedEmailMessage(Message $message): string
{
    $logger = new class extends NullLogger
    {
        public string $loggedValue = '';

        public function log($level, string|\Stringable $message, array $context = []): void
        {
            $this->loggedValue = (string) $message;
        }
    };

    (new LogTransport($logger))->send(
        $message->getSymfonyMessage()
    );

    return $logger->loggedValue;
}
