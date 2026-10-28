<?php

namespace Venusian\Serve\Tests\Session;

use Voyager\Filesystem\Filesystem;
use Mariner\Session\FileSessionHandler;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery;
use function Voyager\Filesystem\join_paths;

beforeEach(function () {
    // Create a mock for the Filesystem class
    $this->files = Mockery::mock(Filesystem::class);

    // Initialize the FileSessionHandler with the mocked Filesystem
    $this->sessionHandler = new FileSessionHandler($this->files, '/path/to/sessions', 30);
});

test('open', function () {
    expect($this->sessionHandler->open('/path/to/sessions', 'session_name'))->toBeTrue();
});

test('close', function () {
    expect($this->sessionHandler->close())->toBeTrue();
});

test('it creates session ids', function () {
    $sessionId = $this->sessionHandler->create_sid();

    expect($sessionId)->toBeString();
    expect($sessionId)->not->toBeEmpty();
});

test('validate id checks file exists', function () {
    $sessionId = 'session_id';
    $path = '/path/to/sessions/'.$sessionId;

    $this->files->expects('isFile')->with($path)->andReturn(true);

    expect($this->sessionHandler->validateId($sessionId))->toBeTrue();
});

test('read returns data when file exists and is valid', function () {
    $sessionId = 'session_id';
    $path = '/path/to/sessions/'.$sessionId;
    Carbon::setTestNow(Carbon::parse('2025-02-02 01:30:00'));
    // Set up expectations
    $this->files->expects('isFile')->with($path)->andReturn(true);

    $minutesAgo30 = Carbon::parse('2025-02-02 01:00:00')->getTimestamp();
    $this->files->expects('lastModified')->with($path)->andReturn($minutesAgo30);
    $this->files->expects('sharedGet')->with($path)->andReturn('session_data');

    $result = $this->sessionHandler->read($sessionId);

    expect($result)->toBe('session_data');
});

test('read returns data when file exists but expired', function () {
    $sessionId = 'session_id';
    $path = '/path/to/sessions/'.$sessionId;
    Carbon::setTestNow(Carbon::parse('2025-02-02 01:30:01'));
    // Set up expectations
    $this->files->expects('isFile')->with($path)->andReturn(true);

    $minutesAgo30 = Carbon::parse('2025-02-02 01:00:00')->getTimestamp();
    $this->files->expects('lastModified')->with($path)->andReturn($minutesAgo30);
    $this->files->shouldReceive('sharedGet')->never();

    $result = $this->sessionHandler->read($sessionId);

    expect($result)->toBe('');
});

test('read returns empty string when file does not exist', function () {
    $sessionId = 'non_existing_session_id';
    $path = '/path/to/sessions/'.$sessionId;

    // Set up expectations
    $this->files->expects('isFile')->with($path)->andReturn(false);

    $result = $this->sessionHandler->read($sessionId);

    expect($result)->toBe('');
});

test('write stores data', function () {
    $sessionId = 'session_id';
    $data = 'session_data';

    // Set up expectations
    $this->files->expects('put')->with('/path/to/sessions/'.$sessionId, $data, true)->andReturn(strlen($data));

    $result = $this->sessionHandler->write($sessionId, $data);

    expect($result)->toBeTrue();
});

test('destroy deletes session file', function () {
    $sessionId = 'session_id';

    // Set up expectations
    $this->files->expects('delete')->with('/path/to/sessions/'.$sessionId)->andReturn(true);

    $result = $this->sessionHandler->destroy($sessionId);

    expect($result)->toBeTrue();
});

test('gc deletes old session files', function () {
    $session = new FileSessionHandler($this->files, join_paths(__DIR__, 'tmp'), 30);
    // Set up expectations for Filesystem
    $this->files->expects('delete')->with(join_paths(__DIR__, 'tmp', 'a2'))->andReturn(false);
    $this->files->expects('delete')->with(join_paths(__DIR__, 'tmp', 'a3'))->andReturn(true);

    mkdir(__DIR__.'/tmp');
    touch(__DIR__.'/tmp/a1', time() - 3); // last modified: 3 sec ago
    touch(__DIR__.'/tmp/a2', time() - 5); // last modified: 5 sec ago
    touch(__DIR__.'/tmp/a3', time() - 7); // last modified: 7 sec ago

    // act:
    $count = $session->gc(5);

    expect($count)->toEqual(2);

    unlink(__DIR__.'/tmp/a1');
    unlink(__DIR__.'/tmp/a2');
    unlink(__DIR__.'/tmp/a3');

    rmdir(__DIR__.'/tmp');
});
