<?php

namespace Venusian\Serve\Tests\Mail;

use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Mail\MailManager;
use Mariner\Mail\Transport\CloudflareTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

test('get transport', function () {
    $container = new ControlPanel;

    $container->registerSingleton('config', function () {
        return new Repository([
            'services' => [
                'cloudflare' => [
                    'account_id' => 'test-account-id',
                    'token' => 'test-token',
                ],
            ],
        ]);
    });

    $manager = new MailManager($container);

    $transport = $manager->createSymfonyTransport(['transport' => 'cloudflare']);

    expect($transport)->toBeInstanceOf(CloudflareTransport::class);
    expect((string) $transport)->toBe('cloudflare');
});

test('send', function () {
    $requestBody = null;
    $requestUrl = null;
    $requestHeaders = null;

    $client = new MockHttpClient(function ($method, $url, $options) use (&$requestBody, &$requestUrl, &$requestHeaders) {
        $requestUrl = $url;
        $requestBody = json_decode($options['body'], true);
        $requestHeaders = $options['normalized_headers'];

        return new MockResponse(json_encode([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => [
                'delivered' => ['me@example.com'],
                'permanent_bounces' => [],
                'queued' => [],
            ],
        ]), ['http_code' => 200]);
    });

    $transport = new CloudflareTransport('test-account-id', 'test-key', $client);

    $message = new Email();
    $message->subject('Test subject');
    $message->html('<p>Hello</p>');
    $message->text('Hello');
    $message->sender('sender@example.com');
    $message->to('me@example.com');
    $message->cc('cc@example.com');
    $message->bcc('bcc@example.com');
    $message->replyTo('taylor@example.com');
    $message->getHeaders()->addTextHeader('X-Custom-Header', 'CustomValue');

    $transport->send($message);

    expect($requestUrl)->toContain('test-account-id');
    expect($requestUrl)->toBe('https://api.cloudflare.com/client/v4/accounts/test-account-id/email/sending/send');
    expect($requestBody['from'])->toBe('sender@example.com');
    expect($requestBody['to'])->toBe(['me@example.com']);
    expect($requestBody['cc'])->toBe(['cc@example.com']);
    expect($requestBody['bcc'])->toBe(['bcc@example.com']);
    expect($requestBody['reply_to'])->toBe('taylor@example.com');
    expect($requestBody['subject'])->toBe('Test subject');
    expect($requestBody['html'])->toBe('<p>Hello</p>');
    expect($requestBody['text'])->toBe('Hello');
    expect($requestBody['headers']['X-Custom-Header'])->toBe('CustomValue');
    expect($requestHeaders)->toHaveKey('authorization');
    expect($requestHeaders['authorization'])->toBe(['Authorization: Bearer test-key']);
});

test('send with named addresses', function () {
    $requestBody = null;

    $client = new MockHttpClient(function ($method, $url, $options) use (&$requestBody) {
        $requestBody = json_decode($options['body'], true);

        return new MockResponse(json_encode([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => [
                'delivered' => ['me@example.com'],
                'permanent_bounces' => [],
                'queued' => [],
            ],
        ]), ['http_code' => 200]);
    });

    $transport = new CloudflareTransport('test-account-id', 'test-key', $client);

    $message = new Email();
    $message->subject('Test subject');
    $message->text('Hello');
    $message->sender(new Address('sender@example.com', 'Taylor Otwell'));
    $message->to('me@example.com');
    $message->replyTo(new Address('taylor@example.com', 'Taylor Otwell'));

    $transport->send($message);

    expect($requestBody['from'])->toBe([
        'name' => 'Taylor Otwell',
        'address' => 'sender@example.com',
    ]);
    expect($requestBody['reply_to'])->toBe([
        'name' => 'Taylor Otwell',
        'address' => 'taylor@example.com',
    ]);
});

test('send with attachment', function () {
    $requestBody = null;

    $client = new MockHttpClient(function ($method, $url, $options) use (&$requestBody) {
        $requestBody = json_decode($options['body'], true);

        return new MockResponse(json_encode([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['delivered' => ['me@example.com'], 'permanent_bounces' => [], 'queued' => []],
        ]), ['http_code' => 200]);
    });

    $transport = new CloudflareTransport('test-account-id', 'test-key', $client);

    $message = new Email();
    $message->subject('With attachment');
    $message->text('See attached');
    $message->sender('sender@example.com');
    $message->to('me@example.com');
    $message->attach('file contents', 'document.txt', 'text/plain');

    $transport->send($message);

    expect($requestBody['attachments'])->toHaveCount(1);
    expect($requestBody['attachments'][0]['filename'])->toBe('document.txt');
    expect($requestBody['attachments'][0]['type'])->toBe('text/plain');
    expect($requestBody['attachments'][0]['disposition'])->toBe('attachment');
    expect($requestBody['attachments'][0]['content'])->not->toBeEmpty();
});

test('send with inline attachment', function () {
    $requestBody = null;

    $client = new MockHttpClient(function ($method, $url, $options) use (&$requestBody) {
        $requestBody = json_decode($options['body'], true);

        return new MockResponse(json_encode([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => ['delivered' => ['me@example.com'], 'permanent_bounces' => [], 'queued' => []],
        ]), ['http_code' => 200]);
    });

    $transport = new CloudflareTransport('test-account-id', 'test-key', $client);

    $message = new Email();
    $message->subject('With inline attachment');
    $message->text('See image');
    $message->sender('sender@example.com');
    $message->to('me@example.com');
    $message->embed('file contents', 'image.png', 'image/png');

    $transport->send($message);

    expect($requestBody['attachments'])->toHaveCount(1);
    expect($requestBody['attachments'][0]['filename'])->toBe('image.png');
    expect($requestBody['attachments'][0]['type'])->toBe('image/png');
    expect($requestBody['attachments'][0]['disposition'])->toBe('inline');
    expect($requestBody['attachments'][0]['content_id'])->toBe('image.png');
    expect($requestBody['attachments'][0]['content'])->not->toBeEmpty();
});

test('send throws on api failure', function () {
    $client = new MockHttpClient(function () {
        return new MockResponse(json_encode([
            'success' => false,
            'errors' => [
                [
                    'code' => 10001,
                    'message' => 'invalid_request_schema',
                ],
            ],
            'messages' => [],
            'result' => null,
        ]), ['http_code' => 400]);
    });

    $transport = new CloudflareTransport('test-account-id', 'test-key', $client);

    $message = new Email();
    $message->subject('Fail');
    $message->text('Body');
    $message->sender('sender@example.com');
    $message->to('me@example.com');

    $transport->send($message);
})->throws(TransportException::class, 'invalid_request_schema', 400);
