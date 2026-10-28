<?php

namespace Venusian\Serve\Tests\Integration\Http\Middleware;

use Voyager\Encryption\Encrypter;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PreventRequestForgeryExceptStub;

beforeEach(function () {

    PreventRequestForgeryExceptStub::except(['/globally/ignored']);
    $this->stub = new PreventRequestForgeryExceptStub(app(), new Encrypter(Encrypter::generateKey('AES-128-CBC'), 'AES-128-CBC'));
    $this->request = Request::create('http://example.com/foo/bar', 'POST');
});

test('it can except paths', function () {
    assertMatchingExcept($this, ['/foo/bar']);
    assertMatchingExcept($this, ['foo/bar']);
    assertNonMatchingExcept($this, ['/bar/foo']);
});

test('paths can be globally ignored', function () {
    $this->request = Request::create('http://example.com/globally/ignored', 'POST');
    assertMatchingExcept($this, ['globally/ignored']);
});

test('it can except wildcard paths', function () {
    assertMatchingExcept($this, ['/foo/*']);
    assertNonMatchingExcept($this, ['/bar*']);
});

test('it can except full url paths', function () {
    assertMatchingExcept($this, ['http://example.com/foo/bar']);
    assertMatchingExcept($this, ['http://example.com/foo/bar/']);

    assertNonMatchingExcept($this, ['https://example.com/foo/bar/']);
    assertNonMatchingExcept($this, ['http://foobar.com/']);
});

test('it can except full url wildcard paths', function () {
    assertMatchingExcept($this, ['http://example.com/*']);
    assertMatchingExcept($this, ['*example.com*']);

    $this->request = Request::create('https://example.com', 'POST');
    assertMatchingExcept($this, ['*example.com']);
});

function assertMatchingExcept($test, array $except, $bool = true)
{
    expect($test->stub->setExcept($except)->checkInExceptArray($test->request))->toBe($bool);
}

function assertNonMatchingExcept($test, array $except)
{
    assertMatchingExcept($test, $except, false);
}
