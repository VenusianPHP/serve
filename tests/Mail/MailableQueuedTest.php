<?php

namespace Venusian\Serve\Tests\Mail;

use Voyager\Bus\Queueable;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Queue\ShouldQueue;
use Mariner\Contracts\View\Factory;
use Voyager\Filesystem\FilesystemManager;
use Voyager\Core\RenderedInstance;
use Mariner\Mail\Mailable;
use Mariner\Mail\Mailer;
use Mariner\Mail\SendQueuedMailable;
use Voyager\Queue\Attributes\Connection;
use Voyager\Queue\Attributes\Delay;
use Voyager\Queue\Attributes\DeleteWhenMissingModels;
use Voyager\Queue\Attributes\FailOnTimeout;
use Voyager\Queue\Attributes\Queue as QueueAttribute;
use Venusian\Serve\Tests\Fixtures\QueueRecorder;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Laravel\SerializableClosure\SerializableClosure;
use LogicException;
use Mockery;
use Symfony\Component\Mailer\Transport\TransportInterface;

test('queued mailable sent', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStub;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);
});

test('queued mailable with attachment sent', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStub;
    $attachmentOption = ['mime' => 'image/jpeg', 'as' => 'bar.jpg'];
    $mailable->attach('foo.jpg', $attachmentOption);
    expect($mailable->attachments)->toBeArray();
    expect($mailable->attachments)->toHaveCount(1);
    expect($attachmentOption)->toEqual($mailable->attachments[0]['options']);
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);
});

test('queued mailable with attachment from disk sent', function () {
    $app = WebApp::boot();
    $container = ControlPanel::getInstance();
    $filesystemFactory = $this->getMockBuilder(FilesystemManager::class)
        ->setConstructorArgs([$app])
        ->getMock();
    $container->registerInstance('filesystem', $filesystemFactory);
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStub;
    $attachmentOption = ['mime' => 'image/jpeg', 'as' => 'bar.jpg'];

    $mailable->attachFromStorage('/', 'foo.jpg', $attachmentOption);

    expect($mailable->diskAttachments)->toBeArray();
    expect($mailable->diskAttachments)->toHaveCount(1);
    expect($attachmentOption)->toEqual($mailable->diskAttachments[0]['options']);

    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    WebApp::tearDown($this);
});

test('queued mailable forwards message group from method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    $mailable = $this->getMockBuilder(MailableQueueableStubWithMessageGroup::class)->onlyMethods(['messageGroup'])->getMock();
    $mailable->expects($this->once())->method('messageGroup')->willReturn($mockedMessageGroupId);

    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->messageGroup)->toEqual($mockedMessageGroupId);
});

test('queued mailable forwards message group from property overriding method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    // Ensure the messageGroup method is not called when a messageGroup property is provided.
    $mailable = $this->getMockBuilder(MailableQueueableStubWithMessageGroup::class)->onlyMethods(['messageGroup'])->getMock();
    $mailable->expects($this->never())->method('messageGroup')->willReturn('this-should-not-be-used');
    $mailable->onGroup($mockedMessageGroupId);

    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->messageGroup)->toEqual($mockedMessageGroupId);
});

test('queued mailable forwards deduplicator to queue job', function () {
    $mockedDeduplicator = fn ($payload, $queue) => 'deduplication-id-1';

    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = (new MailableQueueableStub)->withDeduplicator($mockedDeduplicator);
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->deduplicator)->toBeInstanceOf(SerializableClosure::class);
    expect($pushedJob->deduplicator->getClosure())->toEqual($mockedDeduplicator);
});

test('queued mailable respects delay attribute', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStubWithDelayAttribute;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->delay)->toEqual(30);
});

test('queued mailable delay property overrides attribute', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStubWithDelayAttribute;
    $mailable->delay = 60;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->delay)->toEqual(60);
});

test('queued mailable respects queue and connection attributes', function () {
    $queueFake = new MailableQueueFake;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStubWithQueueAndConnectionAttributes;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn('mail-queue', SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($queueFake->connectionName)->toBe('redis');
    expect($pushedJob->queue)->toBe('mail-queue');
    expect($pushedJob->connection)->toBe('redis');
});

test('delayed queued mailable respects queue and connection attributes', function () {
    $queueFake = new MailableQueueFake;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStubWithDelayQueueAndConnectionAttributes;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn('delayed-mail-queue', SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($queueFake->connectionName)->toBe('sqs');
    expect($pushedJob->queue)->toBe('delayed-mail-queue');
    expect($pushedJob->connection)->toBe('sqs');
    expect($pushedJob->delay)->toEqual(30);
});

test('queued mailable forwards deduplication id method to queue job', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);
    $mailable = new MailableQueueableStubWithDeduplication;
    $queueFake->assertNothingPushed();
    $mailer->send($mailable);
    $queueFake->assertPushedOn(null, SendQueuedMailable::class);

    $pushedJob = $queueFake->pushed(SendQueuedMailable::class)->first();
    expect($pushedJob->deduplicator)->toBeInstanceOf(SerializableClosure::class);
    expect(($pushedJob->deduplicator->getClosure())('payload', 'queue'))->toBe($mailable->deduplicationId('payload', 'queue'));
});

test('queue sets backed enum queue on mailable', function () {
    $queueFake = new QueueRecorder;
    $mailer = new Mailer(...getMocks());
    $mailer->setQueue($queueFake);

    $mailer->queue(new MailableQueueableStub, MailableQueue::Emails);

    $queueFake->assertPushedOn('emails', SendQueuedMailable::class);
});

test('later sets queue on mailable', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);

    $mailable = new MailableQueueableStub;
    $mailer->later(60, $mailable, 'emails');

    $queueFake->assertPushed(SendQueuedMailable::class, function ($job) {
        return $job->queue === 'emails';
    });
});

test('later without queue uses default', function () {
    $queueFake = new QueueRecorder;
    $mailer = $this->getMockBuilder(Mailer::class)
        ->setConstructorArgs(getMocks())
        ->onlyMethods(['createMessage', 'to'])
        ->getMock();
    $mailer->setQueue($queueFake);

    $mailable = new MailableQueueableStub;
    $mailer->later(60, $mailable);

    $queueFake->assertPushed(SendQueuedMailable::class, function ($job) {
        return $job->queue === null;
    });
});

test('queued mailable respects delete when missing models and fail on timeout attributes', function () {
    $job = new SendQueuedMailable(new MailableQueueableStubWithFailureAttributes);

    expect($job->deleteWhenMissingModels)->toBeTrue();
    expect($job->failOnTimeout)->toBeTrue();
});

test('queued mailable respects delete when missing models and fail on timeout properties', function () {
    $job = new SendQueuedMailable(new MailableQueueableStubWithFailureProperties);

    expect($job->deleteWhenMissingModels)->toBeTrue();
    expect($job->failOnTimeout)->toBeTrue();
});

test('a queued mailable cannot be handled directly', function () {
    (new MailableQueueableStub)->handle();
})->throws(LogicException::class, 'cannot be handled directly: a queued mailable is run by SendQueuedMailable');

function getMocks()
{
    return ['smtp', Mockery::mock(Factory::class), Mockery::mock(TransportInterface::class)];
}

class MailableQueueableStub extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        $this
            ->subject('lorem ipsum')
            ->html('foo bar baz')
            ->to('foo@example.tld');

        return $this;
    }
}

enum MailableQueue: string
{
    case Emails = 'emails';
}

class MailableQueueableStubWithMessageGroup extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        $this
            ->subject('lorem ipsum')
            ->html('foo bar baz')
            ->to('foo@example.tld');

        return $this;
    }

    public function messageGroup(): string
    {
        return 'group-1';
    }
}

#[Delay(30)]
class MailableQueueableStubWithDelayAttribute extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        $this
            ->subject('lorem ipsum')
            ->html('foo bar baz')
            ->to('foo@example.tld');

        return $this;
    }
}

class MailableQueueableStubWithDeduplication extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        $this
            ->subject('lorem ipsum')
            ->html('foo bar baz')
            ->to('foo@example.tld');

        return $this;
    }

    public function deduplicationId($payload, $queue)
    {
        return hash('sha256', $payload);
    }
}

#[Connection('redis')]
#[QueueAttribute('mail-queue')]
class MailableQueueableStubWithQueueAndConnectionAttributes extends MailableQueueableStub
{
    //
}

#[DeleteWhenMissingModels]
#[FailOnTimeout]
class MailableQueueableStubWithFailureAttributes extends MailableQueueableStub
{
    //
}

class MailableQueueableStubWithFailureProperties extends MailableQueueableStub
{
    public $deleteWhenMissingModels = true;

    public $failOnTimeout = true;
}

#[Connection('sqs')]
#[Delay(30)]
#[QueueAttribute('delayed-mail-queue')]
class MailableQueueableStubWithDelayQueueAndConnectionAttributes extends MailableQueueableStub
{
    //
}

class MailableQueueFake extends QueueRecorder
{
    public $connectionName;

    public function connection(?string $value = null): \Voyager\Contracts\Queue\Queue
    {
        $this->connectionName = $value;

        return parent::connection($value);
    }
}
