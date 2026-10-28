<?php

namespace Venusian\Serve\Tests\Routing;

use Voyager\Database\Instrument\Model;
use Mariner\Routing\RouteAction;
use Laravel\SerializableClosure\SerializableClosure;

test('it can detect a serialized closure', function () {
    $callable = function (RouteActionUser $user) {
        return $user;
    };

    $action = ['uses' => serialize(
        new SerializableClosure($callable)
    )];

    expect(RouteAction::containsSerializedClosure($action))->toBeTrue();

    $action = ['uses' => 'FooController@index'];

    expect(RouteAction::containsSerializedClosure($action))->toBeFalse();
});

class RouteActionUser extends Model
{
    //
}
