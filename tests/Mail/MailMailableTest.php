<?php

namespace Venusian\Serve\Tests\Mail;

use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Mail\Attachable;
use Mariner\Contracts\View\Factory;
use Mariner\Mail\Attachment;
use Mariner\Mail\Mailable;
use Mariner\Mail\Mailables\Envelope;
use Mariner\Mail\Mailables\Headers;
use Mariner\Mail\Mailer;
use Mariner\Mail\Transport\ArrayTransport;
use Mockery;
use PHPUnit\Framework\AssertionFailedError;
use WeakReference;

test('mailable sets recipients correctly', function () {
    stubMailer();

    $mailable = new WelcomeMailableStub;
    $mailable->to('taylor@laravel.com');
    expect($mailable->to)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com');
    $mailable->to('taylor@laravel.com', 'Taylor Otwell');

    // Add the same recipient again, but with a different name. This should set the name correctly.
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com', 'Taylor Otwell');

    $mailable = new WelcomeMailableStub;
    $mailable->to('taylor@laravel.com', 'Taylor Otwell');
    expect($mailable->to)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->to(['taylor@laravel.com']);
    expect($mailable->to)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeFalse();
    $mailable->assertHasTo('taylor@laravel.com');
    try {
        $mailable->assertHasTo('taylor@laravel.com', 'Taylor Otwell');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not see expected recipient in email 'to' recipients.\nExpected: [taylor@laravel.com (Taylor Otwell)]\nActual: [taylor@laravel.com]\nFailed asserting that false is true.");
    }

    $mailable = new WelcomeMailableStub;
    $mailable->to([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
    expect($mailable->to)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->to(new MailableTestUserStub);
    expect($mailable->to)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->to(collect([new MailableTestUserStub]));
    expect($mailable->to)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->to(collect([new MailableTestUserStub, new MailableTestUserStub, new MailableTestUserStub2]));
    expect($mailable->to)->toEqual([
        ['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com'],
        ['name' => 'Laravel Framework', 'address' => 'contact@laravel.com'],
    ]);
    expect($mailable->hasTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasTo('taylor@laravel.com');

    foreach (['', null, [], false] as $address) {
        $mailable = new WelcomeMailableStub;
        $mailable->to($address);
        expect($mailable->hasTo(new MailableTestUserStub))->toBeFalse();
        expect($mailable->hasTo($address))->toBeFalse();
        try {
            $mailable->assertHasTo($address);
            $this->fail();
        } catch (AssertionFailedError $e) {
            if (! is_string($address)) {
                $address = json_encode($address);
            }
            expect($e->getMessage())->toBe("Did not see expected recipient in email 'to' recipients.\nExpected: [{$address}]\nActual: [none]\nFailed asserting that false is true.");
        }
    }
});

test('mailable sets cc recipients correctly', function () {
    stubMailer();

    $mailable = new WelcomeMailableStub;
    $mailable->cc('taylor@laravel.com');
    expect($mailable->cc)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc('taylor@laravel.com', 'Taylor Otwell');
    expect($mailable->cc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc(['taylor@laravel.com']);
    expect($mailable->cc)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com', 'Taylor Otwell'))->toBeFalse();
    $mailable->assertHasCc('taylor@laravel.com');
    try {
        $mailable->assertHasCc('taylor@laravel.com', 'Taylor Otwell');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not see expected recipient in email 'cc' recipients.\nExpected: [taylor@laravel.com (Taylor Otwell)]\nActual: [taylor@laravel.com]\nFailed asserting that false is true.");
    }

    $mailable = new WelcomeMailableStub;
    $mailable->cc([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
    expect($mailable->cc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc(new MailableTestUserStub);
    expect($mailable->cc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc(collect([new MailableTestUserStub]));
    expect($mailable->cc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasCc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc(collect([new MailableTestUserStub, new MailableTestUserStub, new MailableTestUserStub2]));
    expect($mailable->cc)->toEqual([
        ['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com'],
        ['name' => 'Laravel Framework', 'address' => 'contact@laravel.com'],
    ]);
    expect($mailable->hasCc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->cc(['taylor@laravel.com', 'not-taylor@laravel.com']);
    expect($mailable->cc)->toEqual([
        ['name' => null, 'address' => 'taylor@laravel.com'],
        ['name' => null, 'address' => 'not-taylor@laravel.com'],
    ]);
    expect($mailable->hasCc('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasCc('not-taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasCc('taylor@laravel.com');
    $mailable->assertHasCc('not-taylor@laravel.com');

    foreach (['', null, [], false] as $address) {
        $mailable = new WelcomeMailableStub;
        $mailable->cc($address);
        expect($mailable->hasCc(new MailableTestUserStub))->toBeFalse();
        expect($mailable->hasCc($address))->toBeFalse();
        try {
            $mailable->assertHasCc($address);
            $this->fail();
        } catch (AssertionFailedError $e) {
            if (! is_string($address)) {
                $address = json_encode($address);
            }
            expect($e->getMessage())->toBe("Did not see expected recipient in email 'cc' recipients.\nExpected: [{$address}]\nActual: [none]\nFailed asserting that false is true.");
        }
    }
});

test('mailable sets bcc recipients correctly', function () {
    stubMailer();

    $mailable = new WelcomeMailableStub;
    $mailable->bcc('taylor@laravel.com');
    expect($mailable->bcc)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc('taylor@laravel.com', 'Taylor Otwell');
    expect($mailable->bcc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc(['taylor@laravel.com']);
    expect($mailable->bcc)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com', 'Taylor Otwell'))->toBeFalse();
    $mailable->assertHasBcc('taylor@laravel.com');
    try {
        $mailable->assertHasBcc('taylor@laravel.com', 'Taylor Otwell');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not see expected recipient in email 'bcc' recipients.\nExpected: [taylor@laravel.com (Taylor Otwell)]\nActual: [taylor@laravel.com]\nFailed asserting that false is true.");
    }

    $mailable = new WelcomeMailableStub;
    $mailable->bcc([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
    expect($mailable->bcc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc(new MailableTestUserStub);
    expect($mailable->bcc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc(collect([new MailableTestUserStub]));
    expect($mailable->bcc)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasBcc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc(collect([new MailableTestUserStub, new MailableTestUserStub, new MailableTestUserStub2]));
    expect($mailable->bcc)->toEqual([
        ['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com'],
        ['name' => 'Laravel Framework', 'address' => 'contact@laravel.com'],
    ]);
    expect($mailable->hasBcc(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->bcc(['taylor@laravel.com', 'not-taylor@laravel.com']);
    expect($mailable->bcc)->toEqual([
        ['name' => null, 'address' => 'taylor@laravel.com'],
        ['name' => null, 'address' => 'not-taylor@laravel.com'],
    ]);
    expect($mailable->hasBcc('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasBcc('not-taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasBcc('taylor@laravel.com');
    $mailable->assertHasBcc('not-taylor@laravel.com');

    foreach (['', null, [], false] as $address) {
        $mailable = new WelcomeMailableStub;
        $mailable->bcc($address);
        expect($mailable->hasBcc(new MailableTestUserStub))->toBeFalse();
        expect($mailable->hasBcc($address))->toBeFalse();
        try {
            $mailable->assertHasBcc($address);
            $this->fail();
        } catch (AssertionFailedError $e) {
            if (! is_string($address)) {
                $address = json_encode($address);
            }
            expect($e->getMessage())->toBe("Did not see expected recipient in email 'bcc' recipients.\nExpected: [{$address}]\nActual: [none]\nFailed asserting that false is true.");
        }
    }
});

test('mailable sets reply to correctly', function () {
    stubMailer();

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo('taylor@laravel.com');
    expect($mailable->replyTo)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo('taylor@laravel.com', 'Taylor Otwell');
    expect($mailable->replyTo)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertHasReplyTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo(['taylor@laravel.com']);
    expect($mailable->replyTo)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com', 'Taylor Otwell'))->toBeFalse();
    $mailable->assertHasReplyTo('taylor@laravel.com');
    try {
        $mailable->assertHasReplyTo('taylor@laravel.com', 'Taylor Otwell');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not see expected address as email 'reply to' recipient.\nExpected: [taylor@laravel.com (Taylor Otwell)]\nActual: [taylor@laravel.com]\nFailed asserting that false is true.");
    }

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
    expect($mailable->replyTo)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com');
    $mailable->assertHasReplyTo('taylor@laravel.com', 'Taylor Otwell');

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo(new MailableTestUserStub);
    expect($mailable->replyTo)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo(collect([new MailableTestUserStub]));
    expect($mailable->replyTo)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasReplyTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->replyTo(collect([new MailableTestUserStub, new MailableTestUserStub, new MailableTestUserStub2]));
    expect($mailable->replyTo)->toEqual([
        ['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com'],
        ['name' => 'Laravel Framework', 'address' => 'contact@laravel.com'],
    ]);
    expect($mailable->hasReplyTo(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasReplyTo('taylor@laravel.com'))->toBeTrue();
    $mailable->assertHasReplyTo('taylor@laravel.com');

    foreach (['', null, [], false] as $address) {
        $mailable = new WelcomeMailableStub;
        $mailable->replyTo($address);
        expect($mailable->hasReplyTo(new MailableTestUserStub))->toBeFalse();
        expect($mailable->hasReplyTo($address))->toBeFalse();
        try {
            $mailable->assertHasReplyTo($address);
            $this->fail();
        } catch (AssertionFailedError $e) {
            if (! is_string($address)) {
                $address = json_encode($address);
            }
            expect($e->getMessage())->toBe("Did not see expected address as email 'reply to' recipient.\nExpected: [{$address}]\nActual: [none]\nFailed asserting that false is true.");
        }
    }
});

test('mailable sets from correctly', function () {
    stubMailer();

    $mailable = new WelcomeMailableStub;
    $mailable->from('taylor@laravel.com');
    expect($mailable->from)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->from('taylor@laravel.com', 'Taylor Otwell');
    expect($mailable->from)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertFrom('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->from(['taylor@laravel.com']);
    expect($mailable->from)->toEqual([['name' => null, 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com', 'Taylor Otwell'))->toBeFalse();
    $mailable->assertFrom('taylor@laravel.com');
    try {
        $mailable->assertFrom('taylor@laravel.com', 'Taylor Otwell');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Email was not from expected address.\nExpected: [taylor@laravel.com (Taylor Otwell)]\nActual: [taylor@laravel.com]\nFailed asserting that false is true.");
    }

    $mailable = new WelcomeMailableStub;
    $mailable->from([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
    expect($mailable->from)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com', 'Taylor Otwell');
    $mailable->assertFrom('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->from(new MailableTestUserStub);
    expect($mailable->from)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->from(collect([new MailableTestUserStub]));
    expect($mailable->from)->toEqual([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']]);
    expect($mailable->hasFrom(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com');

    $mailable = new WelcomeMailableStub;
    $mailable->from(collect([new MailableTestUserStub, new MailableTestUserStub, new MailableTestUserStub2]));
    expect($mailable->from)->toEqual([
        ['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com'],
        ['name' => 'Laravel Framework', 'address' => 'contact@laravel.com'],
    ]);
    expect($mailable->hasFrom(new MailableTestUserStub))->toBeTrue();
    expect($mailable->hasFrom('taylor@laravel.com'))->toBeTrue();
    $mailable->assertFrom('taylor@laravel.com');

    foreach (['', null, [], false] as $address) {
        $mailable = new WelcomeMailableStub;
        $mailable->from($address);
        expect($mailable->hasFrom(new MailableTestUserStub))->toBeFalse();
        expect($mailable->hasFrom($address))->toBeFalse();
        try {
            $mailable->assertFrom($address);
            $this->fail();
        } catch (AssertionFailedError $e) {
            if (! is_string($address)) {
                $address = json_encode($address);
            }
            expect($e->getMessage())->toBe("Email was not from expected address.\nExpected: [{$address}]\nActual: [none]\nFailed asserting that false is true.");
        }
    }
});

test('mailable sets subject correctly', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->subject('foo');
    expect($mailable->hasSubject('foo'))->toBeTrue();
});

test('it ignores duplicated raw attachments', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->attachData('content1', 'report-1.txt');
    expect($mailable->rawAttachments)->toHaveCount(1);

    $mailable->attachData('content2', 'report-2.txt');
    expect($mailable->rawAttachments)->toHaveCount(2);

    $mailable->attachData('content1', 'report-1.txt');
    $mailable->attachData('content2', 'report-2.txt');
    expect($mailable->rawAttachments)->toHaveCount(2);

    $mailable->attachData('content1', 'report-3.txt');
    $mailable->attachData('content2', 'report-4.txt');
    expect($mailable->rawAttachments)->toHaveCount(4);

    expect($mailable->rawAttachments)->toBe([
        [
            'data' => 'content1',
            'name' => 'report-1.txt',
            'options' => [],
        ],
        [
            'data' => 'content2',
            'name' => 'report-2.txt',
            'options' => [],
        ],
        [
            'data' => 'content1',
            'name' => 'report-3.txt',
            'options' => [],
        ],
        [
            'data' => 'content2',
            'name' => 'report-4.txt',
            'options' => [],
        ],
    ]);
});

test('it ignores duplicate storage attachments', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->attachFromStorageDisk('disk1', 'sample/file.txt');
    expect($mailable->diskAttachments)->toHaveCount(1);

    $mailable->attachFromStorageDisk('disk1', 'sample/file2.txt');
    expect($mailable->diskAttachments)->toHaveCount(2);

    $mailable->attachFromStorageDisk('disk1', 'sample/file.txt', 'file.txt');
    $mailable->attachFromStorageDisk('disk1', 'sample/file2.txt');
    expect($mailable->diskAttachments)->toHaveCount(2);

    $mailable->attachFromStorageDisk('disk2', 'sample/file.txt', 'file.txt');
    $mailable->attachFromStorageDisk('disk2', 'sample/file2.txt');
    expect($mailable->diskAttachments)->toHaveCount(4);

    $mailable->attachFromStorageDisk('disk1', 'sample/file.txt', 'custom.txt');
    expect($mailable->diskAttachments)->toHaveCount(5);

    expect($mailable->diskAttachments)->toBe([
        [
            'disk' => 'disk1',
            'path' => 'sample/file.txt',
            'name' => 'file.txt',
            'options' => [],
        ],
        [
            'disk' => 'disk1',
            'path' => 'sample/file2.txt',
            'name' => 'file2.txt',
            'options' => [],
        ],
        [
            'disk' => 'disk2',
            'path' => 'sample/file.txt',
            'name' => 'file.txt',
            'options' => [],
        ],
        [
            'disk' => 'disk2',
            'path' => 'sample/file2.txt',
            'name' => 'file2.txt',
            'options' => [],
        ],
        [
            'disk' => 'disk1',
            'path' => 'sample/file.txt',
            'name' => 'custom.txt',
            'options' => [],
        ],
    ]);
});

test('mailable builds view data', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->build();

    $expected = [
        'first_name' => 'Taylor',
        'lastName' => 'Otwell',
        'framework' => 'Laravel',
        '__mariner_mailable' => get_class($mailable),
    ];

    expect($mailable->buildViewData())->toBe($expected);
});

test('mailer may be set', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->mailer('array');
    expect($mailable->usesMailer('array'))->toBeTrue();

    $mailable->mailer('smtp');
    expect($mailable->usesMailer('smtp'))->toBeTrue();
    expect($mailable->usesMailer('ses'))->toBeFalse();
});

test('mailable priority gets sent', function () {
    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $mailable = new WelcomeMailableStub;
    $mailable->to('hello@laravel.com');
    $mailable->from('taylor@laravel.com');
    $mailable->html('test content');

    $mailable->priority(1);

    $sentMessage = $mailer->send($mailable);

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('hello@laravel.com');
    expect($sentMessage->toString())->toContain('X-Priority: 1 (Highest)');
});

test('mailable is released without garbage collection after setting priority', function () {
    $mailer = new Mailer('array', Mockery::mock(Factory::class), new ArrayTransport);

    gc_disable();

    try {
        $mailable = new WelcomeMailableStub;
        $mailable->to('hello@laravel.com');
        $mailable->from('taylor@laravel.com');
        $mailable->html('test content');
        $mailable->priority(1);

        $mailer->send($mailable);

        $reference = WeakReference::create($mailable);
        unset($mailable);

        expect($reference->get())->toBeNull();
    } finally {
        gc_enable();
    }
});

test('mailable metadata gets sent', function () {
    stubMailer();

    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $mailable = new WelcomeMailableStub;
    $mailable->to('hello@laravel.com');
    $mailable->from('taylor@laravel.com');
    $mailable->html('test content');

    $mailable->metadata('origin', 'test-suite');
    $mailable->metadata('user_id', 1);

    $sentMessage = $mailer->send($mailable);

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('hello@laravel.com');
    expect($sentMessage->toString())->toContain('X-Metadata-origin: test-suite');
    expect($sentMessage->toString())->toContain('X-Metadata-user_id: 1');

    expect($mailable->hasMetadata('origin', 'test-suite'))->toBeTrue();
    expect($mailable->hasMetadata('user_id', 1))->toBeTrue();
    expect($mailable->hasMetadata('test', 'test'))->toBeFalse();
    $mailable->assertHasMetadata('origin', 'test-suite');
    $mailable->assertHasMetadata('user_id', 1);
    try {
        $mailable->assertHasMetadata('test', 'test');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Email metadata does not match expected value.\nExpected: [test] => [test]\nActual: key [test] not found\nFailed asserting that false is true.");
    }
});

test('mailable merge metadata', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->to('hello@laravel.com');
    $mailable->from('taylor@laravel.com');
    $mailable->html('test content');

    $mailable->metadata([
        'template_id' => 'external-template-id',
        'customer_id' => 101,
        'order_id' => 1000,
        'subtotal' => 1500,
        'gst' => 150,
        'shipping_fee' => 20,
        'total' => 1670,
    ]);

    expect($mailable->hasMetadata('template_id', 'external-template-id'))->toBeTrue();
    expect($mailable->hasMetadata('customer_id', 101))->toBeTrue();
    expect($mailable->hasMetadata('order_id', 1000))->toBeTrue();
    expect($mailable->hasMetadata('subtotal', 1500))->toBeTrue();
    expect($mailable->hasMetadata('gst', 150))->toBeTrue();
    expect($mailable->hasMetadata('shipping_fee', 20))->toBeTrue();
    expect($mailable->hasMetadata('total', 1670))->toBeTrue();

    stubMailer();
    $view = Mockery::mock(Factory::class);
    $mailer = new Mailer('array', $view, new ArrayTransport);

    $sentMessage = $mailer->send($mailable);
    expect($sentMessage->toString())->toContain('X-Metadata-template_id: external-template-id');
    expect($sentMessage->toString())->toContain('X-Metadata-customer_id: 101');
    expect($sentMessage->toString())->toContain('X-Metadata-order_id: 1000');
    expect($sentMessage->toString())->toContain('X-Metadata-subtotal: 1500');
    expect($sentMessage->toString())->toContain('X-Metadata-gst: 150');
    expect($sentMessage->toString())->toContain('X-Metadata-shipping_fee: 20');
    expect($sentMessage->toString())->toContain('X-Metadata-total: 1670');
});

test('mailable tag gets sent', function () {
    stubMailer();

    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $mailable = new WelcomeMailableStub;
    $mailable->to('hello@laravel.com');
    $mailable->from('taylor@laravel.com');
    $mailable->html('test content');

    $mailable->tag('test');
    $mailable->tag('foo');

    $sentMessage = $mailer->send($mailable);

    expect($sentMessage->getEnvelope()->getRecipients()[0]->getAddress())->toBe('hello@laravel.com');
    expect($sentMessage->toString())->toContain('X-Tag: test');
    expect($sentMessage->toString())->toContain('X-Tag: foo');

    expect($mailable->hasTag('test'))->toBeTrue();
    expect($mailable->hasTag('foo'))->toBeTrue();
    expect($mailable->hasTag('bar'))->toBeFalse();
    $mailable->assertHasTag('test');
    $mailable->assertHasTag('foo');
    try {
        $mailable->assertHasTag('bar');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not see expected tag in email tags.\nExpected: [bar]\nActual: [test, foo]\nFailed asserting that false is true.");
    }
});

test('it can attach multiple files', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->attachMany([
        '/forge.svg',
        '/vapor.svg' => ['as' => 'Vapor Logo.svg', 'mime' => 'text/css'],
        new class() implements Attachable
        {
            public function toMailAttachment()
            {
                return Attachment::fromPath('/foo.jpg')->as('bar')->withMime('image/png');
            }
        },
    ]);

    expect($mailable->attachments)->toHaveCount(3);
    expect($mailable->attachments[0])->toBe([
        'file' => '/forge.svg',
        'options' => [],
    ]);
    expect($mailable->attachments[1])->toBe([
        'file' => '/vapor.svg',
        'options' => [
            'as' => 'Vapor Logo.svg',
            'mime' => 'text/css',
        ],
    ]);
    expect($mailable->attachments[2])->toBe([
        'file' => '/foo.jpg',
        'options' => [
            'as' => 'bar',
            'mime' => 'image/png',
        ],
    ]);
});

test('it attaches files via attachable contract from path', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath('/foo.jpg')->as('bar')->withMime('image/png');
        }
    });

    expect($mailable->attachments[0])->toBe([
        'file' => '/foo.jpg',
        'options' => [
            'as' => 'bar',
            'mime' => 'image/png',
        ],
    ]);
});

test('it attaches files via attachable contract from data', function () {
    $mailable = new WelcomeMailableStub;

    $mailable->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar', 'foo.jpg')->withMime('image/png');
        }
    });

    expect($mailable->rawAttachments[0])->toBe([
        'data' => 'bar',
        'name' => 'foo.jpg',
        'options' => [
            'mime' => 'image/png',
        ],
    ]);
});

test('it can jit name attachments', function () {
    $mailable = new WelcomeMailableStub;
    $unnamedAttachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar')->withMime('image/png');
        }
    };

    $mailable->attach($unnamedAttachable, ['as' => 'foo.jpg']);

    expect($mailable->rawAttachments[0])->toBe([
        'data' => 'bar',
        'name' => 'foo.jpg',
        'options' => [
            'mime' => 'image/png',
        ],
    ]);
});

test('has attachment with jit named attachment', function () {
    $mailable = new WelcomeMailableStub;
    $unnamedAttachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar')->withMime('image/png');
        }
    };

    $mailable->attach($unnamedAttachable, ['as' => 'foo.jpg']);

    expect($mailable->hasAttachment($unnamedAttachable, ['as' => 'foo.jpg']))->toBeTrue();
});

test('has attachment with envelope attachments', function () {
    stubMailer();
    $mailable = new class extends Mailable
    {
        public function envelope()
        {
            return new Envelope();
        }

        public function attachments()
        {
            return [
                Attachment::fromData(fn () => 'bar')
                    ->withMime('image/png')
                    ->as('foo.jpg'),
            ];
        }
    };
    $unnamedAttachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar');
        }
    };

    $mailable->render();

    expect($mailable->hasAttachment($unnamedAttachable))->toBeFalse();
    expect($mailable->hasAttachment($unnamedAttachable, ['as' => 'foo.jpg']))->toBeFalse();
    expect($mailable->hasAttachment($unnamedAttachable, ['mime' => 'image/png']))->toBeFalse();
    expect($mailable->hasAttachment($unnamedAttachable, ['as' => 'foo.jpg', 'mime' => 'image/png']))->toBeTrue();
});

test('it can check for path based attachments', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->attach('foo.jpg');

    expect($mailable->hasAttachment('foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('foo.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')->withMime('text/css')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg')->withMime('text/css'))))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attach('bar.jpg', ['mime' => 'text/css']);

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/css')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/css'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/html']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/html')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/html'))))->toBeFalse();
});

test('it can check for attachment based attachments', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->attach(Attachment::fromPath('foo.jpg'));

    expect($mailable->hasAttachment('foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('foo.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')->withMime('text/css')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg')->withMime('text/css'))))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attach(Attachment::fromPath('bar.jpg')->withMime('text/css'));

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/css')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/css'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/html']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/html')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/html'))))->toBeFalse();
});

test('it can check for attachable based attachments', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->attach(new MailTestAttachable(Attachment::fromPath('foo.jpg')));

    expect($mailable->hasAttachment('foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('foo.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('foo.jpg')->withMime('text/css')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('foo.jpg')->withMime('text/css'))))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attach(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/css')));

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/css')))->toBeTrue();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/css'))))->toBeTrue();

    expect($mailable->hasAttachment('bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg'))))->toBeFalse();

    expect($mailable->hasAttachment('bar.jpg', ['mime' => 'text/html']))->toBeFalse();
    expect($mailable->hasAttachment(Attachment::fromPath('bar.jpg')->withMime('text/html')))->toBeFalse();
    expect($mailable->hasAttachment(new MailTestAttachable(Attachment::fromPath('bar.jpg')->withMime('text/html'))))->toBeFalse();
});

test('it can check for data based attachments', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->attachData('data', 'foo.jpg');

    expect($mailable->hasAttachedData('data', 'foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachedData('xxxx', 'foo.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'foo.jpg', ['mime' => 'text/css']))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attachData('data', 'bar.jpg', ['mime' => 'text/css']);

    expect($mailable->hasAttachedData('data', 'bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachedData('xxxx', 'bar.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg', ['mime' => 'text/html']))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attach(Attachment::fromData(fn () => 'data', 'foo.jpg'));

    expect($mailable->hasAttachedData('data', 'foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachedData('xxxx', 'foo.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'foo.jpg', ['mime' => 'text/css']))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attach(Attachment::fromData(fn () => 'data', 'bar.jpg')->withMime('text/css'));

    expect($mailable->hasAttachedData('data', 'bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachedData('xxxx', 'bar.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachedData('data', 'bar.jpg', ['mime' => 'text/html']))->toBeFalse();
});

test('it can check for storage based attachments', function () {
    $mailable = new WelcomeMailableStub;
    $mailable->attachFromStorageDisk('disk', '/path/to/foo.jpg');

    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg'))->toBeTrue();
    expect($mailable->hasAttachmentFromStorageDisk('xxxx', '/path/to/foo.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', null, ['mime' => 'text/css']))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attachFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg');

    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg'))->toBeTrue();
    expect($mailable->hasAttachmentFromStorageDisk('xxxx', '/path/to/foo.jpg', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', 'bar.jpg', 'bar.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'foo.jpg'))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg', ['mime' => 'text/css']))->toBeFalse();

    $mailable = new WelcomeMailableStub;
    $mailable->attachFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg', ['mime' => 'text/css']);

    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg', ['mime' => 'text/css']))->toBeTrue();
    expect($mailable->hasAttachmentFromStorageDisk('xxxx', '/path/to/foo.jpg', 'bar.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', 'bar.jpg', 'bar.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'foo.jpg', ['mime' => 'text/css']))->toBeFalse();
    expect($mailable->hasAttachmentFromStorageDisk('disk', '/path/to/foo.jpg', 'bar.jpg', ['mime' => 'text/html']))->toBeFalse();
});

test('assert has no attachments', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            //
        }
    };

    $mailable->assertHasNoAttachments();

    $mailableWithAttachment = new class() extends Mailable
    {
        public function build()
        {
            $this->attach('/path/to/foo.jpg');
        }
    };

    try {
        $mailableWithAttachment->assertHasNoAttachments();
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('Expected no attachments');
    }
});

test('assert has attachment', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            //
        }
    };

    try {
        $mailable->assertHasAttachment('/path/to/foo.jpg');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not find the expected attachment.\nFailed asserting that false is true.");
    }

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            $this->attach('/path/to/foo.jpg');
        }
    };

    $mailable->assertHasAttachment('/path/to/foo.jpg');
});

test('assert has attached data', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            //
        }
    };

    try {
        $mailable->assertHasAttachedData('data', 'foo.jpg');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not find the expected attachment.\nFailed asserting that false is true.");
    }

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            $this->attachData('data', 'foo.jpg');
        }
    };

    $mailable->assertHasAttachedData('data', 'foo.jpg');
});

test('assert has attachment from storage', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            //
        }
    };

    try {
        $mailable->assertHasAttachmentFromStorage('/path/to/foo.jpg');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toBe("Did not find the expected attachment.\nFailed asserting that false is true.");
    }

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            $this->attachFromStorage('/path/to/foo.jpg');
        }
    };

    $mailable->assertHasAttachmentFromStorage('/path/to/foo.jpg');
});

test('assert has subject', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            //
        }
    };

    try {
        $mailable->assertHasSubject('Foo Subject');
        $this->fail();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain("Email subject does not match expected value.\nExpected: [Foo Subject]\nActual:");
    }

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            $this->subject('Foo Subject');
        }
    };

    $mailable->assertHasSubject('Foo Subject');
});

test('mailable headers get sent', function () {
    $view = Mockery::mock(Factory::class);

    $mailer = new Mailer('array', $view, new ArrayTransport);

    $mailable = new MailableHeadersStub;
    $mailable->to('hello@laravel.com');
    $mailable->from('taylor@laravel.com');
    $mailable->html('test content');

    $sentMessage = $mailer->send($mailable);

    expect($sentMessage->getMessageId())->toBe('custom-message-id@example.com');

    expect($sentMessage->getOriginalMessage()->getHeaders()->has('references'))->toBeTrue();
    expect($sentMessage->getOriginalMessage()->getHeaders()->get('references')->getName())->toBe('References');
    expect($sentMessage->getOriginalMessage()->getHeaders()->get('references')->getValue())->toBe('<previous-message@example.com>');

    expect($sentMessage->getOriginalMessage()->getHeaders()->has('x-custom-header'))->toBeTrue();
    expect($sentMessage->getOriginalMessage()->getHeaders()->get('x-custom-header')->getName())->toBe('X-Custom-Header');
    expect($sentMessage->getOriginalMessage()->getHeaders()->get('x-custom-header')->getValue())->toBe('Custom Value');
});

test('mailable is released without garbage collection after sending with headers', function () {
    $mailer = new Mailer('array', Mockery::mock(Factory::class), new ArrayTransport);

    gc_disable();

    try {
        $mailable = new MailableHeadersStub;
        $mailable->to('hello@laravel.com');
        $mailable->from('taylor@laravel.com');
        $mailable->html('test content');

        $mailer->send($mailable);

        $reference = WeakReference::create($mailable);
        unset($mailable);

        expect($reference->get())->toBeNull();
    } finally {
        gc_enable();
    }
});

test('mailable attributes in build', function () {
    stubMailer();

    $mailable = new class() extends Mailable
    {
        public function build()
        {
            $this
                ->to('hello@laravel.com')
                ->replyTo('taylor@laravel.com')
                ->cc('cc@laravel.com', 'Taylor Otwell')
                ->bcc('bcc@laravel.com', 'Taylor Otwell')
                ->tag('test-tag')
                ->metadata('origin', 'test-suite')
                ->metadata('user_id', 1)
                ->subject('test subject');
        }
    };

    $mailable->assertTo('hello@laravel.com');
    $mailable->assertHasReplyTo('taylor@laravel.com');
    $mailable->assertHasCc('cc@laravel.com', 'Taylor Otwell');
    $mailable->assertHasBcc('bcc@laravel.com', 'Taylor Otwell');
    $mailable->assertHasTag('test-tag');
    $mailable->assertHasMetadata('origin', 'test-suite');
    $mailable->assertHasMetadata('user_id', 1);
    $mailable->assertHasSubject('test subject');
});

test('mailables can be tapped', function () {
    stubMailer();

    $mail = new WelcomeMailableStub;

    $mail->tap(fn ($mailable) => $mailable->to('taylor@laravel.com', 'Taylor Otwell'));
    $mail->tap(fn ($mailable) => $mailable->subject('Test Subject!'));

    $mail->tap(function ($mailable) {
        $mailable->assertTo('taylor@laravel.com')
            ->assertHasSubject('Test Subject!');
    });
});

function stubMailer()
{
    ControlPanel::getInstance()->registerInstance('mailer', new class
    {
        public function render()
        {
            //
        }
    });
}

class MailableHeadersStub extends Mailable
{
    public function headers()
    {
        return new Headers('custom-message-id@example.com', [
            'previous-message@example.com',
        ], [
            'X-Custom-Header' => 'Custom Value',
        ]);
    }
}

class WelcomeMailableStub extends Mailable
{
    public $framework = 'Laravel';

    protected $version = '5.3';

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->with('first_name', 'Taylor')
            ->withLastName('Otwell');
    }
}

class MailableTestUserStub
{
    public $name = 'Taylor Otwell';
    public $email = 'taylor@laravel.com';
}

class MailableTestUserStub2
{
    public $name = 'Laravel Framework';
    public $email = 'contact@laravel.com';
}

class MailTestAttachable implements Attachable
{
    public function __construct(protected $attachment)
    {
        //
    }

    public function toMailAttachment()
    {
        return $this->attachment;
    }
}

it('answers hasAttachment for a mailable with an envelope() but no attachments()', function () {
    $mailable = new class extends Mailable
    {
        public function envelope(): Envelope
        {
            return new Envelope(subject: 'hi');
        }
    };

    expect($mailable->hasAttachment(Attachment::fromData(fn () => 'x', 'x.txt')))->toBeFalse();
});
