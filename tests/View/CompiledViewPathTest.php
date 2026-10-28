<?php

use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = WebApp::copy();
    (new Filesystem)->deleteDirectory($this->root.'/storage/framework/views');
    file_put_contents($this->root.'/resources/views/plain.blade.php', 'Hi {{ $who }}');
});
afterEach(function () {
    WebApp::tearDown($this);
    (new Filesystem)->deleteDirectory($this->root);
});

it('compiles views into storage/framework/views even before that directory exists', function () {
    $this->app = WebApp::bootFrom($this->root);

    expect(config('view.compiled'))->toBe(realpath($this->root).'/storage/framework/views')
        ->and(view('plain', ['who' => 'Ada'])->render())->toBe('Hi Ada')
        ->and(glob($this->root.'/storage/framework/views/*.php'))->not->toBeEmpty();
});
