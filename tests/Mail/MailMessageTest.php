<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Contracts\Mail\Attachable;
use Mariner\Mail\Attachment;
use Mariner\Mail\Message;
use Voyager\NutsAndBolts\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->message = new Message(new Email());
});

test('from method', function () {
    expect($this->message->from('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getFrom()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('sender method', function () {
    expect($this->message->sender('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getSender())->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('return path method', function () {
    expect($this->message->returnPath('foo@bar.baz'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getReturnPath())->toEqual(new Address('foo@bar.baz'));
});

test('to method', function () {
    expect($this->message->to('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getTo()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));

    expect($this->message->to(['bar@bar.baz' => 'Bar']))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getTo()[0])->toEqual(new Address('bar@bar.baz', 'Bar'));
});

test('to method with override', function () {
    expect($this->message->to('foo@bar.baz', 'Foo', true))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getTo()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('cc method', function () {
    expect($this->message->cc('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getCc()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('bcc method', function () {
    expect($this->message->bcc('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getBcc()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('reply to method', function () {
    expect($this->message->replyTo('foo@bar.baz', 'Foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getReplyTo()[0])->toEqual(new Address('foo@bar.baz', 'Foo'));
});

test('subject method', function () {
    expect($this->message->subject('foo'))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getSubject())->toBe('foo');
});

test('priority method', function () {
    expect($this->message->priority(1))->toBe($this->message);
    expect($this->message->getSymfonyMessage()->getPriority())->toEqual(1);
});

test('basic attachment', function () {
    file_put_contents($path = __DIR__.'/foo.jpg', 'expected attachment body');

    $this->message->attach($path, ['as' => 'bar.jpg', 'mime' => 'image/png']);

    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('expected attachment body');
    expect($headers[0])->toBe('Content-Type: image/png; name=bar.jpg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: attachment; name=bar.jpg; filename=bar.jpg');

    unlink($path);
});

test('data attachment', function () {
    $this->message->attachData('expected attachment body', 'foo.jpg', ['mime' => 'image/png']);

    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('expected attachment body');
    expect($headers[0])->toBe('Content-Type: image/png; name=foo.jpg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: attachment; name=foo.jpg; filename=foo.jpg');
});

test('it attaches files via attachable contract from path', function () {
    file_put_contents($path = __DIR__.'/foo.jpg', 'expected attachment body');

    $this->message->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath(__DIR__.'/foo.jpg')
                ->as('bar.jpg')
                ->withMime('image/png');
        }
    });

    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('expected attachment body');
    expect($headers[0])->toBe('Content-Type: image/png; name=bar.jpg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: attachment; name=bar.jpg; filename=bar.jpg');

    unlink($path);
});

test('it attaches files via attachable contract from data', function () {
    $this->message->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'expected attachment body', 'foo.jpg')
                ->withMime('image/png');
        }
    });

    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('expected attachment body');
    expect($headers[0])->toBe('Content-Type: image/png; name=foo.jpg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: attachment; name=foo.jpg; filename=foo.jpg');
});

test('embed path', function () {
    file_put_contents($path = __DIR__.'/foo.jpg', 'bar');

    $cid = $this->message->embed($path);

    expect($cid)->toStartWith('cid:');
    $contentId = Str::after($cid, 'cid:');
    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('bar');
    expect($attachment->getContentId())->toBe($contentId);
    expect($headers[0])->toContain('Content-Type: image/jpeg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toContain('Content-Disposition: inline');

    unlink($path);
});

test('data embed', function () {
    $cid = $this->message->embedData('bar', 'foo.jpg', 'image/png');

    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($cid)->toStartWith('cid:');
    $contentId = Str::after($cid, 'cid:');
    expect($attachment->getContentId())->toBe($contentId);
    expect($attachment->getBody())->toBe('bar');
    expect($headers[0])->toBe('Content-Type: image/png; name=foo.jpg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: inline; name=foo.jpg; filename=foo.jpg');
});

test('it embeds files via attachable contract from path', function () {
    file_put_contents($path = __DIR__.'/foo.jpg', 'bar');

    $cid = $this->message->embed(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath(__DIR__.'/foo.jpg')->as('baz')->withMime('image/png');
        }
    });

    expect($cid)->toStartWith('cid:');
    $contentId = Str::after($cid, 'cid:');
    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getContentId())->toBe($contentId);
    expect($attachment->getBody())->toBe('bar');
    expect($headers[0])->toBe('Content-Type: image/png; name=baz');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toBe('Content-Disposition: inline; name=baz; filename=baz');

    unlink($path);
});

test('it embeds files via attachable contract from data', function () {
    $cid = $this->message->embed(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar', 'foo.jpg')->withMime('image/png');
        }
    });

    expect($cid)->toStartWith('cid:');
    $contentId = Str::after($cid, 'cid:');
    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getContentId())->toBe($contentId);
    expect($attachment->getBody())->toBe('bar');
    expect($headers[0])->toContain('Content-Type: image/png');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toContain('Content-Disposition: inline');
});

test('it generates arandom name when attachable has none', function () {
    file_put_contents($path = __DIR__.'/foo.jpg', 'bar');

    $cid = $this->message->embed(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath(__DIR__.'/foo.jpg');
        }
    });

    expect($cid)->toStartWith('cid:');
    $contentId = Str::after($cid, 'cid:');
    $attachment = $this->message->getSymfonyMessage()->getAttachments()[0];
    expect($attachment->getContentId())->toBe($contentId);
    $headers = $attachment->getPreparedHeaders()->toArray();
    expect($attachment->getBody())->toBe('bar');
    expect($headers[0])->toContain('Content-Type: image/jpeg');
    expect($headers[1])->toBe('Content-Transfer-Encoding: base64');
    expect($headers[2])->toContain('Content-Disposition: inline');

    unlink($path);
});
