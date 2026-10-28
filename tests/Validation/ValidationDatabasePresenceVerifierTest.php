<?php

namespace Venusian\Serve\Tests\Validation;

use Closure;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\ConnectionResolver;
use Voyager\Database\Query\Builder;
use Mariner\Validation\DatabasePresenceVerifier;
use Mockery;

test('basic count', function () {
    $conn = Mockery::mock(ConnectionInterface::class);
    $db = new ConnectionResolver;
    $db->addConnection('connection', $conn);
    $verifier = new DatabasePresenceVerifier($db);
    $verifier->setConnection('connection');
    $builder = Mockery::mock(Builder::class);
    $conn->expects('table')->with('table')->andReturn($builder);
    $builder->expects('useWritePdo')->andReturn($builder);
    $builder->expects('where')->with('column', '=', 'value')->andReturn($builder);
    $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin'];
    $builder->expects('whereNull')->with('foo');
    $builder->expects('whereNotNull')->with('bar');
    $builder->expects('where')->with('baz', 'taylor');
    $builder->expects('where')->with('faz', true);
    $builder->expects('where')->with('not', '!=', 'admin');
    $builder->expects('count')->andReturn(100);

    expect($verifier->getCount('table', 'column', 'value', null, null, $extra))->toEqual(100);
});

test('basic count with closures', function () {
    $conn = Mockery::mock(ConnectionInterface::class);
    $db = new ConnectionResolver;
    $db->addConnection('connection', $conn);
    $verifier = new DatabasePresenceVerifier($db);
    $verifier->setConnection('connection');
    $builder = Mockery::mock(Builder::class);
    $conn->expects('table')->with('table')->andReturn($builder);
    $builder->expects('useWritePdo')->andReturn($builder);
    $builder->expects('where')->with('column', '=', 'value')->andReturn($builder);
    $closure = function ($query) {
        $query->where('closure', 1);
    };
    $extra = ['foo' => 'NULL', 'bar' => 'NOT_NULL', 'baz' => 'taylor', 'faz' => true, 'not' => '!admin', 0 => $closure];
    $builder->expects('whereNull')->with('foo');
    $builder->expects('whereNotNull')->with('bar');
    $builder->expects('where')->with('baz', 'taylor');
    $builder->expects('where')->with('faz', true);
    $builder->expects('where')->with('not', '!=', 'admin');
    $builder->expects('where')->with(Mockery::type(Closure::class))->andReturnUsing(function () use ($builder, $closure) {
        $closure($builder);
    });
    $builder->expects('where')->with('closure', 1);
    $builder->expects('count')->andReturn(100);

    expect($verifier->getCount('table', 'column', 'value', null, null, $extra))->toEqual(100);
});

test('get count with valid exclude id', function () {
    $conn = Mockery::mock(ConnectionInterface::class);
    $db = new ConnectionResolver;
    $db->addConnection('connection', $conn);
    $verifier = new DatabasePresenceVerifier($db);
    $verifier->setConnection('connection');
    $builder = Mockery::mock(Builder::class);
    $conn->expects('table')->with('table')->andReturn($builder);
    $builder->expects('useWritePdo')->andReturn($builder);
    $builder->expects('where')->with('column', '=', 'value')->andReturn($builder);
    $builder->expects('where')->with('id', '<>', 123)->andReturn($builder);
    $builder->expects('count')->andReturn(100);

    expect($verifier->getCount('table', 'column', 'value', 123, 'id', []))->toEqual(100);
});
