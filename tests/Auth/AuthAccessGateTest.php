<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\Access\Gate;
use Mariner\Auth\Access\HandlesAuthorization;
use Mariner\Auth\Access\Response;
use Voyager\Vessel\ControlPanel;
use Venusian\Serve\Tests\Auth\Fixtures\AbilitiesEnum;
use InvalidArgumentException;
use stdClass;

require_once __DIR__.'/Fixtures/Enums.php';

test('basic closures can be defined', function () {
    $gate = getBasicGate();

    $gate->define('foo', function ($user) {
        return true;
    });
    $gate->define('bar', function ($user) {
        return false;
    });

    expect($gate->check('foo'))->toBeTrue();
    expect($gate->check('bar'))->toBeFalse();
});

test('before can take an array callback as object', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->before([new AccessGateTestBeforeCallback, 'allowEverything']);

    expect($gate->check('anything'))->toBeTrue();
});

test('before can take an array callback as object static', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->before([new AccessGateTestBeforeCallback, 'allowEverythingStatically']);

    expect($gate->check('anything'))->toBeTrue();
});

test('before can take an array callback with static method', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->before([AccessGateTestBeforeCallback::class, 'allowEverythingStatically']);

    expect($gate->check('anything'))->toBeTrue();
});

test('before can allow guests', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->before(function (?stdClass $user) {
        return true;
    });

    expect($gate->check('anything'))->toBeTrue();
});

test('after can allow guests', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->after(function (?stdClass $user) {
        return true;
    });

    expect($gate->check('anything'))->toBeTrue();
});

test('closures can allow guest users', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->define('foo', function (?stdClass $user) {
        return true;
    });

    $gate->define('bar', function (stdClass $user) {
        return false;
    });

    expect($gate->check('foo'))->toBeTrue();
    expect($gate->check('bar'))->toBeFalse();
});

test('policies can allow guests', function () {
    unset($_SERVER['__laravel.testBefore']);

    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyThatAllowsGuests::class);

    expect($gate->check('edit', new AccessGateTestDummy))->toBeTrue();
    expect($gate->check('update', new AccessGateTestDummy))->toBeFalse();
    expect($_SERVER['__laravel.testBefore'])->toBeTrue();

    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyThatAllowsGuests::class);

    expect($gate->check('edit', new AccessGateTestDummy))->toBeTrue();
    expect($gate->check('update', new AccessGateTestDummy))->toBeTrue();

    unset($_SERVER['__laravel.testBefore']);
});

test('policy before not called with guests if it doesnt allow them', function () {
    $_SERVER['__laravel.testBefore'] = false;

    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithNonGuestBefore::class);

    expect($gate->check('edit', new AccessGateTestDummy))->toBeTrue();
    expect($gate->check('update', new AccessGateTestDummy))->toBeFalse();
    expect($_SERVER['__laravel.testBefore'])->toBeFalse();

    unset($_SERVER['__laravel.testBefore']);
});

test('before and after callbacks can allow guests', function () {
    $_SERVER['__laravel.gateBefore'] = false;
    $_SERVER['__laravel.gateBefore2'] = false;
    $_SERVER['__laravel.gateAfter'] = false;
    $_SERVER['__laravel.gateAfter2'] = false;

    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->before(function (?stdClass $user) {
        $_SERVER['__laravel.gateBefore'] = true;
    });

    $gate->after(function (?stdClass $user) {
        $_SERVER['__laravel.gateAfter'] = true;
    });

    $gate->before(function (stdClass $user) {
        $_SERVER['__laravel.gateBefore2'] = true;
    });

    $gate->after(function (stdClass $user) {
        $_SERVER['__laravel.gateAfter2'] = true;
    });

    $gate->define('foo', function ($user = null) {
        return true;
    });

    expect($gate->check('foo'))->toBeTrue();

    expect($_SERVER['__laravel.gateBefore'])->toBeTrue();
    expect($_SERVER['__laravel.gateBefore2'])->toBeFalse();
    expect($_SERVER['__laravel.gateAfter'])->toBeTrue();
    expect($_SERVER['__laravel.gateAfter2'])->toBeFalse();

    unset(
        $_SERVER['__laravel.gateBefore'],
        $_SERVER['__laravel.gateBefore2'],
        $_SERVER['__laravel.gateAfter'],
        $_SERVER['__laravel.gateAfter2']
    );
});

test('resource gates can be defined', function () {
    $gate = getBasicGate();

    $gate->resource('test', AccessGateTestResource::class);

    $dummy = new AccessGateTestDummy;

    expect($gate->check('test.view'))->toBeTrue();
    expect($gate->check('test.create'))->toBeTrue();
    expect($gate->check('test.update', $dummy))->toBeTrue();
    expect($gate->check('test.delete', $dummy))->toBeTrue();
});

test('custom resource gates can be defined', function () {
    $gate = getBasicGate();

    $abilities = [
        'ability1' => 'foo',
        'ability2' => 'bar',
    ];

    $gate->resource('test', AccessGateTestCustomResource::class, $abilities);

    expect($gate->check('test.ability1'))->toBeTrue();
    expect($gate->check('test.ability2'))->toBeTrue();
});

test('before callbacks can override result if necessary', function () {
    $gate = getBasicGate();

    $gate->define('foo', function ($user) {
        return true;
    });
    $gate->before(function ($user, $ability) {
        expect($ability)->toBe('foo');

        return false;
    });

    expect($gate->check('foo'))->toBeFalse();
});

test('before callbacks dont interrupt gate check if no value is returned', function () {
    $gate = getBasicGate();

    $gate->define('foo', function ($user) {
        return true;
    });
    $gate->before(function () {
        //
    });

    expect($gate->check('foo'))->toBeTrue();
});

test('after callbacks are called with result', function () {
    $gate = getBasicGate();

    $gate->define('foo', function ($user) {
        return true;
    });

    $gate->define('bar', function ($user) {
        return false;
    });

    $gate->after(function ($user, $ability, $result) {
        if ($ability === 'foo') {
            expect($result)->toBeTrue();
        } elseif ($ability === 'bar') {
            expect($result)->toBeFalse();
        } else {
            expect($result)->toBeNull();
        }
    });

    expect($gate->check('foo'))->toBeTrue();
    expect($gate->check('bar'))->toBeFalse();
    expect($gate->check('missing'))->toBeFalse();
});

test('after callbacks can allow if null', function () {
    $gate = getBasicGate();

    $gate->after(function ($user, $ability, $result) {
        return true;
    });

    expect($gate->allows('null'))->toBeTrue();
});

test('after callbacks do not override previous result', function () {
    $gate = getBasicGate();

    $gate->define('deny', function ($user) {
        return false;
    });

    $gate->define('allow', function ($user) {
        return true;
    });

    $gate->after(function ($user, $ability, $result) {
        return ! $result;
    });

    expect($gate->allows('allow'))->toBeTrue();
    expect($gate->denies('deny'))->toBeTrue();
});

test('after callbacks do not override each other', function () {
    $gate = getBasicGate();

    $gate->after(function ($user, $ability, $result) {
        return $ability === 'allow';
    });

    $gate->after(function ($user, $ability, $result) {
        return ! $result;
    });

    expect($gate->allows('allow'))->toBeTrue();
    expect($gate->denies('deny'))->toBeTrue();
});

test('can define gates using backed enum', function () {
    $gate = getBasicGate();

    $gate->define(AbilitiesEnum::VIEW_DASHBOARD, function ($user) {
        return true;
    });

    expect($gate->allows('view-dashboard'))->toBeTrue();
});

test('backed enum in allows', function () {
    $gate = getBasicGate();

    $gate->define(AbilitiesEnum::VIEW_DASHBOARD, function ($user) {
        return true;
    });

    expect($gate->allows(AbilitiesEnum::VIEW_DASHBOARD))->toBeTrue();
});

test('backed enum in denies', function () {
    $gate = getBasicGate();

    $gate->define(AbilitiesEnum::VIEW_DASHBOARD, function ($user) {
        return false;
    });

    expect($gate->denies(AbilitiesEnum::VIEW_DASHBOARD))->toBeTrue();
});

test('array abilities in allows', function () {
    $gate = getBasicGate();

    $gate->define('allow_1', function ($user) {
        return true;
    });
    $gate->define('allow_2', function ($user) {
        return true;
    });
    $gate->define(AbilitiesEnum::VIEW_DASHBOARD, function ($user) {
        return true;
    });
    $gate->define('deny', function ($user) {
        return false;
    });

    expect($gate->allows(['allow_1']))->toBeTrue();
    expect($gate->allows(['allow_1', 'allow_2', AbilitiesEnum::VIEW_DASHBOARD]))->toBeTrue();
    expect($gate->allows(['allow_1', 'allow_2', 'deny']))->toBeFalse();
    expect($gate->allows(['deny', 'allow_1', 'allow_2']))->toBeFalse();
});

test('array abilities in denies', function () {
    $gate = getBasicGate();

    $gate->define('deny_1', function ($user) {
        return false;
    });
    $gate->define('deny_2', function ($user) {
        return false;
    });
    $gate->define(AbilitiesEnum::VIEW_DASHBOARD, function ($user) {
        return false;
    });
    $gate->define('allow', function ($user) {
        return true;
    });

    expect($gate->denies(['deny_1']))->toBeTrue();
    expect($gate->denies(['deny_1', 'deny_2', AbilitiesEnum::VIEW_DASHBOARD]))->toBeTrue();
    expect($gate->denies(['deny_1', 'allow']))->toBeTrue();
    expect($gate->denies(['allow', 'deny_1']))->toBeTrue();
    expect($gate->denies(['allow']))->toBeFalse();
});

test('current user that is on gate always injected into closure callbacks', function () {
    $gate = getBasicGate();

    $gate->define('foo', function ($user) {
        expect($user->id)->toBe(1);

        return true;
    });

    expect($gate->check('foo'))->toBeTrue();
});

test('asingle argument can be passed when checking abilities', function () {
    $gate = getBasicGate();

    $dummy = new AccessGateTestDummy;

    $gate->before(function ($user, $ability, array $arguments) use ($dummy) {
        expect($arguments)->toHaveCount(1);
        expect($arguments[0])->toBe($dummy);
    });

    $gate->define('foo', function ($user, $x) use ($dummy) {
        expect($x)->toBe($dummy);

        return true;
    });

    $gate->after(function ($user, $ability, $result, array $arguments) use ($dummy) {
        expect($arguments)->toHaveCount(1);
        expect($arguments[0])->toBe($dummy);
    });

    expect($gate->check('foo', $dummy))->toBeTrue();
});

test('multiple arguments can be passed when checking abilities', function () {
    $gate = getBasicGate();

    $dummy1 = new AccessGateTestDummy;
    $dummy2 = new AccessGateTestDummy;

    $gate->before(function ($user, $ability, array $arguments) use ($dummy1, $dummy2) {
        expect($arguments)->toHaveCount(2);
        expect($arguments)->toBe([$dummy1, $dummy2]);
    });

    $gate->define('foo', function ($user, $x, $y) use ($dummy1, $dummy2) {
        expect($x)->toBe($dummy1);
        expect($y)->toBe($dummy2);

        return true;
    });

    $gate->after(function ($user, $ability, $result, array $arguments) use ($dummy1, $dummy2) {
        expect($arguments)->toHaveCount(2);
        expect($arguments)->toBe([$dummy1, $dummy2]);
    });

    expect($gate->check('foo', [$dummy1, $dummy2]))->toBeTrue();
});

test('classes can be defined as callbacks using at notation', function () {
    $gate = getBasicGate();

    $gate->define('foo', AccessGateTestClass::class.'@foo');

    expect($gate->check('foo'))->toBeTrue();
});

test('invokable classes can be defined', function () {
    $gate = getBasicGate();

    $gate->define('foo', AccessGateTestInvokableClass::class);

    expect($gate->check('foo'))->toBeTrue();
});

test('gates can be defined using an array callback', function () {
    $gate = getBasicGate();

    $gate->define('foo', [new AccessGateTestStaticClass, 'foo']);

    expect($gate->check('foo'))->toBeTrue();
});

test('gates can be defined using an array callback with static method', function () {
    $gate = getBasicGate();

    $gate->define('foo', [AccessGateTestStaticClass::class, 'foo']);

    expect($gate->check('foo'))->toBeTrue();
});

test('policy classes can be defined to handle checks for given type', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('update', new AccessGateTestDummy))->toBeTrue();
});

test('policy classes handle checks for all subtypes', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('update', new AccessGateTestSubDummy))->toBeTrue();
});

test('policy classes handle checks for interfaces', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummyInterface::class, AccessGateTestPolicy::class);

    expect($gate->check('update', new AccessGateTestSubDummy))->toBeTrue();
});

test('policy converts dash to camel', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('update-dash', new AccessGateTestDummy))->toBeTrue();
});

test('policy default to false if method does not exist and gate does not exist', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('nonexistent_method', new AccessGateTestDummy))->toBeFalse();
});

test('policy classes can be defined to handle checks for given class name', function () {
    $gate = getBasicGate(true);

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('create', [AccessGateTestDummy::class, true]))->toBeTrue();
});

test('policies may have before methods to override checks', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithBefore::class);

    expect($gate->check('update', new AccessGateTestDummy))->toBeTrue();
});

test('policies always override closures with same name', function () {
    $gate = getBasicGate();

    $gate->define('update', function () {
        $this->fail();
    });

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('update', new AccessGateTestDummy))->toBeTrue();
});

test('policies defer to gates if method does not exist', function () {
    $gate = getBasicGate();

    $gate->define('nonexistent_method', function ($user) {
        return true;
    });

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    expect($gate->check('nonexistent_method', new AccessGateTestDummy))->toBeTrue();
});

test('for user method attaches anew user to anew gate instance', function () {
    $gate = getBasicGate();

    // Assert that the callback receives the new user with ID of 2 instead of ID of 1...
    $gate->define('foo', function ($user) {
        expect($user->id)->toBe(2);

        return true;
    });

    expect($gate->forUser((object) ['id' => 2])->check('foo'))->toBeTrue();
});

test('for user method attaches anew user to anew gate instance with guess callback', function () {
    $gate = getBasicGate();

    $gate->define('foo', function () {
        return true;
    });

    $counter = 0;
    $guesserCallback = function () use (&$counter) {
        $counter++;
    };
    $gate->guessPolicyNamesUsing($guesserCallback);
    $gate->getPolicyFor('fooClass');
    expect($counter)->toBe(1);

    // now the guesser callback should be present on the new gate as well
    $newGate = $gate->forUser((object) ['id' => 1]);

    $newGate->getPolicyFor('fooClass');
    expect($counter)->toBe(2);

    $newGate->getPolicyFor('fooClass');
    expect($counter)->toBe(3);
});

test('for user method preserves default denial response', function () {
    $gate = getBasicGate();

    $gate->define('view-secret', fn () => false);
    $gate->defaultDenialResponse(Response::denyAsNotFound('Not found'));

    $response = $gate->forUser((object) ['id' => 2])->inspect('view-secret');

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe('Not found');
    expect($response->status())->toBe(404);
});

test('define second parameter should be string or callable', function ($callback) {

    $gate = getBasicGate();

    $gate->define('foo', $callback);
})->with('notCallableDataProvider')->throws(InvalidArgumentException::class);

dataset('notCallableDataProvider', function () {
    return [
        [1],
        [new stdClass],
        [[]],
        [1.1],
    ];
});

test('authorize throws unauthorized exception', function () {
    expect(function () {
        $gate = getBasicGate();

        $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

        $gate->authorize('create', new AccessGateTestDummy);
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('You are not an admin.', 0);

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('authorize throws unauthorized exception with custom status code', function () {
    expect(function () {
        $gate = getBasicGate();

        $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithCode::class);

        $gate->authorize('view', new AccessGateTestDummy);
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('Not allowed to view as it is not published.', 'unpublished');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('authorize with policy that returns denied response object throws exception', function () {
    expect(function () {
        $gate = getBasicGate();

        $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithDeniedResponseObject::class);

        $gate->authorize('create', new AccessGateTestDummy);
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('Not allowed.', 'some_code');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('policy that throws authorization exception is caught in inspect', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyThrowingAuthorizationException::class);

    $response = $gate->inspect('create', new AccessGateTestDummy);

    expect($response->denied())->toBeTrue();
    expect($response->allowed())->toBeFalse();
    expect($response->message())->toBe('Not allowed.');
    expect($response->code())->toBe('some_code');
});

test('authorize returns allowed response', function () {
    $gate = getBasicGate(true);

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    $check = $gate->check('create', new AccessGateTestDummy);
    $response = $gate->authorize('create', new AccessGateTestDummy);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->message())->toBeNull();
    expect($check)->toBeTrue();
});

test('response returns response when ability granted', function () {
    $gate = getBasicGate(true);

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithCode::class);

    $response = $gate->inspect('view', new AccessGateTestDummy);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->message())->toBeNull();
    expect($response->allowed())->toBeTrue();
    expect($response->denied())->toBeFalse();
    expect($response->code())->toBeNull();
});

test('response returns response when ability denied', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithCode::class);

    $response = $gate->inspect('view', new AccessGateTestDummy);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->message())->toBe('Not allowed to view as it is not published.');
    expect($response->allowed())->toBeFalse();
    expect($response->denied())->toBeTrue();
    expect($response->code())->toBe('unpublished');
});

test('authorize returns an allowed response for atruthy return', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicy::class);

    $response = $gate->authorize('update', new AccessGateTestDummy);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->message())->toBeNull();
});

test('allow if authorizes true', function () {
    $response = getBasicGate()->allowIf(true);

    expect($response->allowed())->toBeTrue();
});

test('allow if authorizes truthy', function () {
    $response = getBasicGate()->allowIf('truthy');

    expect($response->allowed())->toBeTrue();
});

test('allow if authorizes if guest', function () {
    $response = getBasicGate()->forUser(null)->allowIf(true);

    expect($response->allowed())->toBeTrue();
});

test('allow if authorizes callback true', function () {
    $response = getBasicGate()->allowIf(function ($user) {
        expect($user->id)->toBe(1);

        return true;
    }, 'foo', 'bar');

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('foo');
    expect($response->code())->toBe('bar');
});

test('allow if authorizes response allowed', function () {
    $response = getBasicGate()->allowIf(Response::allow('foo', 'bar'));

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('foo');
    expect($response->code())->toBe('bar');
});

test('allow if authorizes callback response allowed', function () {
    $response = getBasicGate()->allowIf(function () {
        return Response::allow('quz', 'qux');
    }, 'foo', 'bar');

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('quz');
    expect($response->code())->toBe('qux');
});

test('allows if callback accepts guests when authenticated', function () {
    $response = getBasicGate()->allowIf(function (?stdClass $user = null) {
        return $user !== null;
    });

    expect($response->allowed())->toBeTrue();
});

test('allow if callback accepts guests when unauthenticated', function () {
    $gate = getBasicGate()->forUser(null);

    $response = $gate->allowIf(function (?stdClass $user = null) {
        return $user === null;
    });

    expect($response->allowed())->toBeTrue();
});

test('allow if throws exception when false', function () {

    getBasicGate()->allowIf(false);
})->throws(AuthorizationException::class);

test('allow if throws exception when callback false', function () {
    expect(function () {
        getBasicGate()->allowIf(function () {
            return false;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('allow if throws exception when response denied', function () {
    expect(function () {
        getBasicGate()->allowIf(Response::deny('foo', 'bar'));
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('allow if throws exception when callback response denied', function () {
    expect(function () {
        getBasicGate()->allowIf(function () {
            return Response::deny('quz', 'qux');
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('quz', 'qux');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('allow if throws exception if unauthenticated', function () {
    expect(function () {
        $gate = getBasicGate()->forUser(null);

        $gate->allowIf(function () {
            return true;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('allow if throws exception if auth user expected when guest', function () {
    expect(function () {
        $gate = getBasicGate()->forUser(null);

        $gate->allowIf(function (stdClass $user) {
            return true;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('deny if authorizes false', function () {
    $response = getBasicGate()->denyIf(false);

    expect($response->allowed())->toBeTrue();
});

test('deny if authorizes falsy', function () {
    $response = getBasicGate()->denyIf(0);

    expect($response->allowed())->toBeTrue();
});

test('deny if authorizes if guest', function () {
    $response = getBasicGate()->forUser(null)->denyIf(false);

    expect($response->allowed())->toBeTrue();
});

test('deny if authorizes callback false', function () {
    $response = getBasicGate()->denyIf(function ($user) {
        expect($user->id)->toBe(1);

        return false;
    }, 'foo', 'bar');

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('foo');
    expect($response->code())->toBe('bar');
});

test('deny if authorizes response allowed', function () {
    $response = getBasicGate()->denyIf(Response::allow('foo', 'bar'));

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('foo');
    expect($response->code())->toBe('bar');
});

test('deny if authorizes callback response allowed', function () {
    $response = getBasicGate()->denyIf(function () {
        return Response::allow('quz', 'qux');
    }, 'foo', 'bar');

    expect($response->allowed())->toBeTrue();
    expect($response->message())->toBe('quz');
    expect($response->code())->toBe('qux');
});

test('deny if callback accepts guests when authenticated', function () {
    $response = getBasicGate()->denyIf(function (?stdClass $user = null) {
        return $user === null;
    });

    expect($response->allowed())->toBeTrue();
});

test('deny if callback accepts guests when unauthenticated', function () {
    $gate = getBasicGate()->forUser(null);

    $response = $gate->denyIf(function (?stdClass $user = null) {
        return $user !== null;
    });

    expect($response->allowed())->toBeTrue();
});

test('deny if throws exception when true', function () {

    getBasicGate()->denyIf(true);
})->throws(AuthorizationException::class);

test('deny if throws exception when callback true', function () {
    expect(function () {
        getBasicGate()->denyIf(function () {
            return true;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('deny if throws exception when response denied', function () {
    expect(function () {
        getBasicGate()->denyIf(Response::deny('foo', 'bar'));
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('deny if throws exception when callback response denied', function () {
    expect(function () {
        getBasicGate()->denyIf(function () {
            return Response::deny('quz', 'qux');
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('quz', 'qux');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('deny if throws exception if unauthenticated', function () {
    expect(function () {
        $gate = getBasicGate()->forUser(null);

        $gate->denyIf(function () {
            return false;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

test('deny if throws exception if auth user expected when guest', function () {
    expect(function () {
        $gate = getBasicGate()->forUser(null);

        $gate->denyIf(function (stdClass $user) {
            return false;
        }, 'foo', 'bar');
    })->toThrow(function (AuthorizationException $e) {
        $expected = new AuthorizationException('foo', 'bar');

        expect([$e->getMessage(), $e->getCode()])->toBe([$expected->getMessage(), $expected->getCode()]);
    });
});

function getBasicGate($isAdmin = false)
{
    return new Gate(new ControlPanel, function () use ($isAdmin) {
        return (object) ['id' => 1, 'isAdmin' => $isAdmin];
    });
}

test('any ability check passes if all pass', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithAllPermissions::class);

    expect($gate->any(['edit', 'update'], new AccessGateTestDummy))->toBeTrue();
});

test('any ability check passes if at least one passes', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithMixedPermissions::class);

    expect($gate->any(['edit', 'update'], new AccessGateTestDummy))->toBeTrue();
});

test('any ability check fails if none pass', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithNoPermissions::class);

    expect($gate->any(['edit', 'update'], new AccessGateTestDummy))->toBeFalse();
});

test('none ability check passes if all fail', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithNoPermissions::class);

    expect($gate->none(['edit', 'update'], new AccessGateTestDummy))->toBeTrue();
});

test('every ability check passes if all pass', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithAllPermissions::class);

    expect($gate->check(['edit', 'update'], new AccessGateTestDummy))->toBeTrue();
});

test('every ability check fails if at least one fails', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithMixedPermissions::class);

    expect($gate->check(['edit', 'update'], new AccessGateTestDummy))->toBeFalse();
});

test('every ability check fails if none pass', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithNoPermissions::class);

    expect($gate->check(['edit', 'update'], new AccessGateTestDummy))->toBeFalse();
});

test('any abilities check using backed enum', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithAllPermissions::class);

    expect($gate->any(['edit', AbilitiesEnum::UPDATE], new AccessGateTestDummy))->toBeTrue();
});

test('none abilities check using backed enum', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithNoPermissions::class);

    expect($gate->none(['edit', AbilitiesEnum::UPDATE], new AccessGateTestDummy))->toBeTrue();
});

test('abilities check using backed enum', function () {
    $gate = getBasicGate();

    $gate->policy(AccessGateTestDummy::class, AccessGateTestPolicyWithAllPermissions::class);

    expect($gate->check(['edit', AbilitiesEnum::UPDATE], new AccessGateTestDummy))->toBeTrue();
});

test('has abilities', function ($abilitiesToSet, $abilitiesToCheck, $expectedHasValue) {
    $gate = getBasicGate();

    $gate->resource('test', AccessGateTestResource::class, $abilitiesToSet);

    expect($gate->has($abilitiesToCheck))->toEqual($expectedHasValue);
})->with('hasAbilitiesTestDataProvider');

dataset('hasAbilitiesTestDataProvider', function () {
    $abilities = ['foo' => 'foo', 'bar' => 'bar'];
    $noAbilities = [];

    return [
        [$abilities, ['test.foo', 'test.bar'], true],
        [$abilities, ['test.bar', 'test.foo'], true],
        [$abilities, ['test.bar', 'test.foo', 'test.baz'], false],
        [$abilities, ['test.bar'], true],
        [$abilities, ['baz'], false],
        [$abilities, [''], false],
        [$abilities, [], true],
        [$abilities, 'test.bar', true],
        [$abilities, 'test.foo', true],
        [$abilities, '', false],
        [$noAbilities, '', false],
        [$noAbilities, [], true],
    ];
});

test('classes can be defined as callbacks using at notation for guests', function () {
    $gate = new Gate(new ControlPanel, function () {
        //
    });

    $gate->define('foo', AccessGateTestClassForGuest::class.'@foo');
    $gate->define('obj_foo', [new AccessGateTestClassForGuest, 'foo']);
    $gate->define('static_foo', [AccessGateTestClassForGuest::class, 'staticFoo']);
    $gate->define('static_@foo', AccessGateTestClassForGuest::class.'@staticFoo');
    $gate->define('bar', AccessGateTestClassForGuest::class.'@bar');
    $gate->define('invokable', AccessGateTestGuestInvokableClass::class);
    $gate->define('nullable_invokable', AccessGateTestGuestNullableInvokable::class);
    $gate->define('absent_invokable', 'someAbsentClass');

    AccessGateTestClassForGuest::$calledMethod = '';

    expect($gate->check('foo'))->toBeTrue();
    expect(AccessGateTestClassForGuest::$calledMethod)->toBe('foo was called');

    expect($gate->check('static_foo'))->toBeTrue();
    expect(AccessGateTestClassForGuest::$calledMethod)->toBe('static foo was invoked');

    expect($gate->check('bar'))->toBeTrue();
    expect(AccessGateTestClassForGuest::$calledMethod)->toBe('bar got invoked');

    expect($gate->check('static_@foo'))->toBeTrue();
    expect(AccessGateTestClassForGuest::$calledMethod)->toBe('static foo was invoked');

    expect($gate->check('invokable'))->toBeTrue();
    expect(AccessGateTestGuestInvokableClass::$calledMethod)->toBe('__invoke was called');

    expect($gate->check('nullable_invokable'))->toBeTrue();
    expect(AccessGateTestGuestNullableInvokable::$calledMethod)->toBe('Nullable __invoke was called');

    expect($gate->check('absent_invokable'))->toBeFalse();
});

test('can set denial response in constructor', function () {
    $gate = new Gate(container: new ControlPanel, userResolver: function () {
        //
    });

    $gate->defaultDenialResponse(Response::denyWithStatus(999, 'my_message', 'abc'));

    $gate->define('foo', function () {
        return false;
    });

    $response = $gate->inspect('foo', new AccessGateTestDummy);

    expect($response->denied())->toBeTrue();
    expect($response->allowed())->toBeFalse();
    expect($response->message())->toBe('my_message');
    expect($response->code())->toBe('abc');
    expect($response->status())->toBe(999);
});

test('can set denial response', function () {
    $gate = new Gate(container: new ControlPanel, userResolver: function () {
        //
    });

    $gate->define('foo', function () {
        return false;
    });
    $gate->defaultDenialResponse(Response::denyWithStatus(404, 'not_found', 'xyz'));

    $response = $gate->inspect('foo', new AccessGateTestDummy);
    expect($response->denied())->toBeTrue();
    expect($response->allowed())->toBeFalse();
    expect($response->message())->toBe('not_found');
    expect($response->code())->toBe('xyz');
    expect($response->status())->toBe(404);
});

class AccessGateTestClassForGuest
{
    public static $calledMethod = null;

    public function foo($user = null)
    {
        static::$calledMethod = 'foo was called';

        return true;
    }

    public static function staticFoo($user = null)
    {
        static::$calledMethod = 'static foo was invoked';

        return true;
    }

    public function bar(?stdClass $user)
    {
        static::$calledMethod = 'bar got invoked';

        return true;
    }
}

class AccessGateTestStaticClass
{
    public static function foo($user)
    {
        return $user->id === 1;
    }
}

class AccessGateTestClass
{
    public function foo($user)
    {
        return $user->id === 1;
    }
}

class AccessGateTestInvokableClass
{
    public function __invoke($user)
    {
        return $user->id === 1;
    }
}

class AccessGateTestGuestInvokableClass
{
    public static $calledMethod = null;

    public function __invoke($user = null)
    {
        static::$calledMethod = '__invoke was called';

        return true;
    }
}

class AccessGateTestGuestNullableInvokable
{
    public static $calledMethod = null;

    public function __invoke(?stdClass $user)
    {
        static::$calledMethod = 'Nullable __invoke was called';

        return true;
    }
}

interface AccessGateTestDummyInterface
{
    //
}

class AccessGateTestDummy implements AccessGateTestDummyInterface
{
    //
}

class AccessGateTestSubDummy extends AccessGateTestDummy
{
    //
}

class AccessGateTestPolicy
{
    use HandlesAuthorization;

    public function createAny($user, $additional)
    {
        return $additional;
    }

    public function create($user)
    {
        return $user->isAdmin ? $this->allow() : $this->deny('You are not an admin.');
    }

    public function updateAny($user, AccessGateTestDummy $dummy)
    {
        return ! $user->isAdmin;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return ! $user->isAdmin;
    }

    public function updateDash($user, AccessGateTestDummy $dummy)
    {
        return $user instanceof stdClass;
    }
}

class AccessGateTestPolicyWithBefore
{
    public function before($user, $ability)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return false;
    }
}

class AccessGateTestResource
{
    public function view($user)
    {
        return true;
    }

    public function create($user)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }

    public function delete($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}

class AccessGateTestCustomResource
{
    public function foo($user)
    {
        return true;
    }

    public function bar($user)
    {
        return true;
    }
}

class AccessGateTestPolicyWithMixedPermissions
{
    public function edit($user, AccessGateTestDummy $dummy)
    {
        return false;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}

class AccessGateTestPolicyWithNoPermissions
{
    public function edit($user, AccessGateTestDummy $dummy)
    {
        return false;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return false;
    }
}

class AccessGateTestPolicyWithAllPermissions
{
    public function edit($user, AccessGateTestDummy $dummy)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}

class AccessGateTestPolicyThatAllowsGuests
{
    public function before(?stdClass $user)
    {
        $_SERVER['__laravel.testBefore'] = true;
    }

    public function edit(?stdClass $user, AccessGateTestDummy $dummy)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}

class AccessGateTestPolicyWithNonGuestBefore
{
    public function before(stdClass $user)
    {
        $_SERVER['__laravel.testBefore'] = true;
    }

    public function edit(?stdClass $user, AccessGateTestDummy $dummy)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}

class AccessGateTestBeforeCallback
{
    public function allowEverything($user = null)
    {
        return true;
    }

    public static function allowEverythingStatically($user = null)
    {
        return true;
    }
}

class AccessGateTestPolicyWithCode
{
    use HandlesAuthorization;

    public function view($user)
    {
        if (! $user->isAdmin) {
            return $this->deny('Not allowed to view as it is not published.', 'unpublished');
        }

        return true;
    }
}

class AccessGateTestPolicyWithDeniedResponseObject
{
    public function create()
    {
        return Response::deny('Not allowed.', 'some_code');
    }
}

class AccessGateTestPolicyThrowingAuthorizationException
{
    public function create()
    {
        throw new AuthorizationException('Not allowed.', 'some_code');
    }
}
