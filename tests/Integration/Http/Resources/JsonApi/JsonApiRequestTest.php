<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi;

use Mariner\Http\Resources\JsonApi\JsonApiRequest;
use Mariner\Http\Resources\JsonApi\JsonApiResource;

test('it can resolve sparse fields', function () {
    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'fields' => [
            'users' => 'name,email',
            'teams' => 'name',
        ],
    ]));

    expect($request->sparseFields('users'))->toBe(['name', 'email']);
    expect($request->sparseFields('teams'))->toBe(['name']);
    expect($request->sparseFields('posts'))->toBe([]);
});

test('it can resolve empty sparse fields', function () {
    $request = JsonApiRequest::create(uri: '/');

    expect($request->sparseFields('users'))->toBe([]);
    expect($request->sparseFields('teams'))->toBe([]);
    expect($request->sparseFields('posts'))->toBe([]);
});

test('it can determine if sparse fieldset was provided', function () {
    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'fields' => [
            'users' => '',
        ],
    ]));

    expect($request->hasSparseFieldset('users'))->toBeTrue();
    expect($request->hasSparseFieldset('posts'))->toBeFalse();
});

test('it ignores non string sparse fields', function () {
    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'fields' => [
            'users' => ['name', 'email'],
        ],
    ]));

    expect($request->sparseFields('users'))->toBe([]);
    expect($request->hasSparseFieldset('users'))->toBeTrue();
});

test('it can resolve sparse included', function () {
    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'include' => 'teams,posts.author,posts.comments,profile.user.profile',
    ]));

    expect($request->sparseIncluded())->toBe(['teams', 'posts', 'profile']);
    expect($request->sparseIncluded('teams'))->toBe([]);
    expect($request->sparseIncluded('posts'))->toBe(['author', 'comments']);
    expect($request->sparseIncluded('profile'))->toBe(['user.profile']);
});

test('it can resolve sparse included with max relationship nesting', function () {
    JsonApiResource::maxRelationshipDepth(2);

    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'include' => 'teams,posts.author,posts.comments,profile.user.profile',
    ]));

    expect($request->sparseIncluded())->toBe(['teams', 'posts', 'profile']);
    expect($request->sparseIncluded('teams'))->toBe([]);
    expect($request->sparseIncluded('posts'))->toBe(['author', 'comments']);
    expect($request->sparseIncluded('profile'))->toBe(['user']);
});

test('it drops nested sparse included with zero max relationship nesting', function () {
    JsonApiResource::maxRelationshipDepth(0);

    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'include' => 'teams,posts.author,profile.user.profile',
    ]));

    expect($request->sparseIncluded())->toBe(['teams', 'posts', 'profile']);
    expect($request->sparseIncluded('teams'))->toBe([]);
    expect($request->sparseIncluded('posts'))->toBe([]);
    expect($request->sparseIncluded('profile'))->toBe([]);
});

test('it can resolve empty sparse included', function () {
    $request = JsonApiRequest::create(uri: '/');

    expect($request->sparseIncluded())->toBe([]);
});

test('it ignores non string sparse included', function () {
    $request = JsonApiRequest::create(uri: '/?'.http_build_query([
        'include' => ['teams', 'posts'],
    ]));

    expect($request->sparseIncluded())->toBe([]);
    expect($request->sparseIncluded('teams'))->toBe([]);
});
