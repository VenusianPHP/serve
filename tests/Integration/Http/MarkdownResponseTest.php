<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Mariner\Http\Request;
use Mariner\NutsAndBolts\Facades\Route;

test('markdown response', function () {
    Route::get('/markdown', function () {
        return response()->markdown("# Hello\n\nWorld");
    });

    $response = $this->get('/markdown');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    $response->assertContent("# Hello\n\nWorld");
});

test('markdown response with status and headers', function () {
    Route::get('/markdown', function () {
        return response()->markdown('# Not Found', 404, [
            'Content-Type' => 'text/html',
            'X-Foo' => 'bar',
        ]);
    });

    $response = $this->get('/markdown');

    $response->assertNotFound();
    $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    $response->assertHeader('X-Foo', 'bar');
    $response->assertContent('# Not Found');
});

test('markdown response can be negotiated', function () {
    Route::get('/markdown', function (Request $request) {
        return $request->wantsMarkdown()
            ? response()->markdown('# Hello')
            : response('<h1>Hello</h1>');
    });

    $this->get('/markdown', ['Accept' => 'text/markdown'])
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertContent('# Hello');

    $this->get('/markdown', ['Accept' => 'text/html'])
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertContent('<h1>Hello</h1>');
});
