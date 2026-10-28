<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Contracts\View\Factory;
use Mariner\Contracts\View\View;
use Voyager\Signals\SignalDispatcher;
use Mariner\Mail\Events\MessageSending;
use Mariner\Mail\Events\MessageSent;
use Mariner\Mail\Mailer;
use Mariner\Mail\Message;
use Mariner\Mail\Transport\ArrayTransport;
use Voyager\NutsAndBolts\HtmlString;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use InvalidArgumentException;
use Mockery;
use Symfony\Component\Mime\Address;

afterEach(function () {
    unset($_SERVER['__mailer.test']);
});

test('mailer send sends message with proper view content', function () {
    $view = viewFactory('rendered.view');

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    expect($sentMessage->toString())->toContain('rendered.view');
});

test('mailer send sends message with cc and bcc recipients', function () {
    $view = viewFactory('rendered.view');

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')
            ->cc('dries@laravel.com')
            ->bcc('james@laravel.com')
            ->from('hello@laravel.com');
    });

    $recipients = collect($sentMessage->getEnvelope()->getRecipients())->map(function ($recipient) {
        return $recipient->getAddress();
    });

    expect($sentMessage->toString())->toContain('rendered.view');
    expect($sentMessage->toString())->toContain('dries@laravel.com');
    expect($sentMessage->toString())->not->toContain('james@laravel.com');
    expect($recipients->contains('james@laravel.com'))->toBeTrue();
});

test('mailer send sends message with proper view content using html strings', function () {
    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send(
        ['html' => new HtmlString('<p>Hello Laravel</p>'), 'text' => new HtmlString('Hello World')],
        ['data'],
        function (Message $message) {
            $message->to('taylor@laravel.com')->from('hello@laravel.com');
        }
    );

    expect($sentMessage->toString())->toContain('<p>Hello Laravel</p>');
    expect($sentMessage->toString())->toContain('Hello World');
});

test('mailer send sends message with proper view content using string callbacks', function () {
    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send(
        [
            'html' => function ($data) {
                expect($data['message'])->toBeInstanceOf(Message::class);

                return new HtmlString('<p>Hello Laravel</p>');
            },
            'text' => function ($data) {
                expect($data['message'])->toBeInstanceOf(Message::class);

                return new HtmlString('Hello World');
            },
        ],
        [],
        function (Message $message) {
            $message->to('taylor@laravel.com')->from('hello@laravel.com');
        }
    );

    expect($sentMessage->toString())->toContain('<p>Hello Laravel</p>');
    expect($sentMessage->toString())->toContain('Hello World');
});

test('mailer send sends message with proper view content using html method', function () {
    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->html('<p>Hello World</p>', function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    expect($sentMessage->toString())->toContain('<p>Hello World</p>');
});

test('mailer send sends message with proper plain view content', function () {
    $view = viewFactory('rendered.view', 'rendered.plain');

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send(['foo', 'bar'], ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    $expected = <<<Text
        Content-Type: text/html; charset=utf-8\r
        Content-Transfer-Encoding: quoted-printable\r
        \r
        rendered.view
        Text;

    expect($sentMessage->toString())->toContain($expected);

    $expected = <<<Text
        Content-Type: text/plain; charset=utf-8\r
        Content-Transfer-Encoding: quoted-printable\r
        \r
        rendered.plain
        Text;

    expect($sentMessage->toString())->toContain($expected);
});

test('mailer send sends message with proper plain view content when explicit', function () {
    $view = viewFactory('rendered.view', 'rendered.plain');

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send(['html' => 'foo', 'text' => 'bar'], ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    $expected = <<<Text
        Content-Type: text/html; charset=utf-8\r
        Content-Transfer-Encoding: quoted-printable\r
        \r
        rendered.view
        Text;

    expect($sentMessage->toString())->toContain($expected);

    $expected = <<<Text
        Content-Type: text/plain; charset=utf-8\r
        Content-Transfer-Encoding: quoted-printable\r
        \r
        rendered.plain
        Text;

    expect($sentMessage->toString())->toContain($expected);
});

test('to allows email and name', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->to('taylor@laravel.com', 'Taylor Otwell')->send(new TestMail());

    $recipients = $sentMessage->getEnvelope()->getRecipients();
    expect($recipients)->toHaveCount(1);
    expect($recipients[0]->getAddress())->toBe('taylor@laravel.com');
    expect($recipients[0]->getName())->toBe('Taylor Otwell');
});

test('mailer rejects addresses containing line breaks', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);

    $mailer->send('foo', ['data'], function (Message $message) {
        $message->to("\"foo\r\nBcc: victim@example.com\"@example.com")->from('hello@laravel.com');
    });
})->throws(InvalidArgumentException::class, 'Email addresses may not contain line break characters.');

test('mailer rejects symfony addresses containing line breaks', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);

    try {
        $mailer->send('foo', ['data'], function (Message $message) {
            $message->to(new Address("\"foo\r\nBcc: victim@example.com\"@example.com"))->from('hello@laravel.com');
        });

        $this->fail('Expected InvalidArgumentException was not thrown.');
    } catch (InvalidArgumentException $e) {
        expect([
            'Email address contains control characters.',
            'Email addresses may not contain line break characters.',
        ])->toContain($e->getMessage());
    }
});

test('global from is respected on all messages', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);
    $mailer->alwaysFrom('hello@laravel.com');

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('taylor@laravel.com');
    });

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('taylor@laravel.com');
    expect($sentMessage->getEnvelope()->getSender()->getAddress())->toBe('hello@laravel.com');
});

test('global reply to is respected on all messages', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);
    $mailer->alwaysReplyTo('taylor@laravel.com', 'Taylor Otwell');

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('dries@laravel.com')->from('hello@laravel.com');
    });

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('dries@laravel.com');
    expect($sentMessage->toString())->toContain('Reply-To: Taylor Otwell <taylor@laravel.com>');
});

test('global to is respected on all messages', function () {
    $view = viewFactory('rendered.view');
    $mailer = new Mailer('array', $view, new ArrayTransport);
    $mailer->alwaysTo('taylor@laravel.com', 'Taylor Otwell');

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->from('hello@laravel.com');
        $message->to('nuno@laravel.com');
        $message->cc('dries@laravel.com');
        $message->bcc('james@laravel.com');
    });

    $recipients = collect($sentMessage->getEnvelope()->getRecipients())->map(function ($recipient) {
        return $recipient->getAddress();
    });

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('taylor@laravel.com');
    expect($sentMessage->toString())->not->toMatch('/^To: nuno@laravel.com/m');
    expect($sentMessage->toString())->not->toMatch('/^Cc: dries@laravel.com/m');
    expect($sentMessage->toString())->toMatch('/^X-To: nuno@laravel.com/m');
    expect($sentMessage->toString())->toMatch('/^X-Cc: dries@laravel.com/m');
    expect($sentMessage->toString())->toMatch('/^X-Bcc: james@laravel.com/m');
    expect($recipients->contains('nuno@laravel.com'))->toBeFalse();
    expect($recipients->contains('dries@laravel.com'))->toBeFalse();
    expect($recipients->contains('james@laravel.com'))->toBeFalse();
});

test('global return path is respected on all messages', function () {
    $view = viewFactory('rendered.view');

    $mailer = new Mailer('array', $view, new ArrayTransport);
    $mailer->alwaysReturnPath('taylorotwell@gmail.com');

    $sentMessage = $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    expect($sentMessage->toString())->toContain('Return-Path: <taylorotwell@gmail.com>');
});

test('events are dispatched', function () {
    $view = viewFactory('rendered.view');

    $events = new SignalRecorder;

    $mailer = new Mailer('array', $view, new ArrayTransport, $events->dispatcher());

    $mailer->send('foo', ['data'], function (Message $message) {
        $message->to('taylor@laravel.com')->from('hello@laravel.com');
    });

    $events->assertDispatchedOnce(MessageSending::class);
    $events->assertDispatchedOnce(MessageSent::class);
});

test('macroable', function () {
    Mailer::macro('foo', function () {
        return 'bar';
    });

    $mailer = new Mailer('array', Mockery::mock(Factory::class), new ArrayTransport);

    expect($mailer->foo())->toBe('bar');
});

function viewFactory(string ...$rendered)
{
    $factory = Mockery::mock(Factory::class);

    foreach ($rendered as $contents) {
        $view = Mockery::mock(View::class);
        $view->expects('render')->andReturn($contents);
        $factory->expects('make')->andReturn($view);
    }

    return $factory;
}

class TestMail extends \Mariner\Mail\Mailable
{
    public function build()
    {
        return $this->view('view')
            ->from('hello@laravel.com');
    }
}
