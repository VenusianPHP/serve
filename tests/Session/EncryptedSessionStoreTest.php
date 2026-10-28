<?php

namespace Venusian\Serve\Tests\Session;

use Voyager\Contracts\Encryption\Encrypter;
use Mariner\Session\EncryptedStore;
use Mockery;
use ReflectionClass;
use SessionHandlerInterface;

test('session is properly encrypted', function () {
    $session = encryptedSession();
    $session->getEncrypter()->expects('decrypt')->with(serialize([]))->andReturn(serialize([]));
    $session->getHandler()->expects('read')->andReturn(serialize([]));
    $session->start();
    $session->put('foo', 'bar');
    $session->flash('baz', 'boom');
    $session->now('qux', 'norf');
    $serialized = serialize([
        '_token' => $session->token(),
        'foo' => 'bar',
        'baz' => 'boom',
        '_flash' => [
            'new' => [],
            'old' => ['baz'],
        ],
    ]);
    $session->getEncrypter()->expects('encrypt')->with($serialized)->andReturn($serialized);
    $session->getHandler()->expects('write')->with(
        encryptedSessionId(),
        $serialized
    );
    $session->save();

    expect($session->isStarted())->toBeFalse();
});

function encryptedSession()
{
    $reflection = new ReflectionClass(EncryptedStore::class);

    return $reflection->newInstanceArgs(encryptedMocks());
}

function encryptedMocks()
{
    return [
        encryptedSessionName(),
        Mockery::mock(SessionHandlerInterface::class),
        Mockery::mock(Encrypter::class),
        encryptedSessionId(),
    ];
}

function encryptedSessionId()
{
    return 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}

function encryptedSessionName()
{
    return 'name';
}
