<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Exception;
use Mariner\Http\StreamedEvent;
use Mockery;
use Psr\Log\LoggerInterface;
use Mariner\NutsAndBolts\Facades\Route;

test('event stream response', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            yield new StreamedEvent(
                event: 'update',
                data: ['message' => 'hello'],
            );

            yield new StreamedEvent(
                event: 'update',
                data: ['message' => 'world'],
            );
        });
    });

    $response = $this->get('/stream');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    $response->assertHeader('X-Accel-Buffering', 'no');

    $content = $response->streamedContent();

    expect($content)->toContain("event: update\n");
    expect($content)->toContain('data: {"message":"hello"}');
    expect($content)->toContain('data: {"message":"world"}');
    expect($content)->toContain('data: </stream>');
});

test('event stream messages with line breaks are sent as multiple data lines', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            yield "Hello\nWorld";

            yield new StreamedEvent(
                event: 'chunk',
                data: "line 1\r\nline 2",
            );
        });
    });

    $content = $this->get('/stream')->streamedContent();

    expect($content)->toContain("event: update\ndata: Hello\ndata: World\n\n");
    expect($content)->toContain("event: chunk\ndata: line 1\ndata: line 2\n\n");
    expect($content)->toContain("event: update\ndata: </stream>\n\n");
});

test('event stream end message with line breaks is sent as multiple data lines', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            yield 'hello';
        }, endStreamWith: new StreamedEvent(event: 'end', data: "done\nbye"));
    });

    $content = $this->get('/stream')->streamedContent();

    expect($content)->toEndWith("event: end\ndata: done\ndata: bye\n\n");
});

test('event stream message cannot start another event', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            yield "harmless\n\nevent: injected\ndata: evil";
        }, endStreamWith: null);
    });

    $content = $this->get('/stream')->streamedContent();

    expect($content)->toBe("event: update\ndata: harmless\ndata: \ndata: event: injected\ndata: data: evil\n\n");
});

test('event stream exception does not leak to client', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            yield new StreamedEvent(
                event: 'update',
                data: ['message' => 'hello'],
            );

            throw new Exception('Something went wrong during streaming');
        });
    });

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->expects('error')->with('Something went wrong during streaming', Mockery::type('array'));
    $this->instance(LoggerInterface::class, $logger);

    $response = $this->get('/stream');
    $content = $response->streamedContent();

    expect($content)->toContain("event: update\n");
    expect($content)->toContain('data: {"message":"hello"}');
    expect($content)->not->toContain('Something went wrong during streaming');
    expect($content)->not->toContain("event: error\n");
    expect($content)->not->toContain('data: </stream>');
});

test('event stream exception is reported to exception handler', function () {
    Route::get('/stream', function () {
        return response()->eventStream(function () {
            throw new Exception('Test exception reporting');
        });
    });

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->expects('error')->with('Test exception reporting', Mockery::type('array'));
    $this->instance(LoggerInterface::class, $logger);

    $response = $this->get('/stream');
    $response->streamedContent();
});
