<?php

namespace Venusian\Serve\Tests\Validation;

use Generator;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Http\Client\ConnectionException;
use Venusian\Serve\Tests\Fixtures\ExceptionRecorder;
use Mariner\Validation\NotPwnedVerifier;
use Voyager\Http\Client\Factory;

afterEach(function () {
    ControlPanel::setInstance(null);
});

test('empty values', function ($password) {
    $http = new Factory;
    $http->fake();
    $verifier = new NotPwnedVerifier($http);

    expect($verifier->verify([
        'value' => $password,
        'threshold' => 0,
    ]))->toBeFalse();

    $http->assertNothingSent();
})->with('dataProviderEmptyValues');

dataset('dataProviderEmptyValues', function () {
    yield 'empty string' => [''];
    yield 'false' => [false];
    yield 'zero' => [0];
});

test('api response goes wrong', function () {
    $http = new Factory;
    $http->fake(['api.pwnedpasswords.com/*' => Factory::response('', 200)]);
    $verifier = new NotPwnedVerifier($http);

    expect($verifier->verify([
        'value' => 123123123,
        'threshold' => 0,
    ]))->toBeTrue();

    $http->assertSentCount(1);
});

test('api goes down', function () {
    $http = new Factory;
    $http->fake(['api.pwnedpasswords.com/*' => Factory::response('', 500)]);
    $verifier = new NotPwnedVerifier($http);

    expect($verifier->verify([
        'value' => 123123123,
        'threshold' => 0,
    ]))->toBeTrue();

    $http->assertSentCount(1);
});

test('magic hash does not cause false positive', function () {
    // "aaroZmOk" produces a SHA-1 hash that is all digits prefixed with "0E",
    // which PHP treats as scientific notation (zero) during loose comparison,
    // causing any other all-digit "0E" hash to falsely match.
    $password = 'aaroZmOk';
    $hash = strtoupper(sha1($password));
    $hashPrefix = substr($hash, 0, 5);

    $differentSuffix = '00000000000000000000000000000000000';

    $http = new Factory;
    $http->fake(['api.pwnedpasswords.com/*' => Factory::response($differentSuffix.':5', 200)]);
    $verifier = new NotPwnedVerifier($http);

    expect($verifier->verify([
        'value' => $password,
        'threshold' => 0,
    ]))->toBeTrue();

    $http->assertSent(fn ($request) => $request->url() === 'https://api.pwnedpasswords.com/range/'.$hashPrefix
        && $request->hasHeader('Add-Padding'));
});

test('dns down', function () {
    $container = ControlPanel::getInstance();

    $exceptionHandler = new ExceptionRecorder;
    $container->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
        return $exceptionHandler;
    });

    $http = new Factory;
    $http->fake(Factory::failedConnection());
    $verifier = new NotPwnedVerifier($http);

    expect($verifier->verify([
        'value' => 123123123,
        'threshold' => 0,
    ]))->toBeTrue();

    $exceptionHandler->assertReported(ConnectionException::class);

    unset($container[ExceptionHandler::class]);
});
