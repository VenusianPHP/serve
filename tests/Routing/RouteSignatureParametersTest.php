<?php

namespace Venusian\Serve\Tests\Routing;

use Voyager\Database\Instrument\Model;
use Mariner\Routing\RouteSignatureParameters;
use Laravel\SerializableClosure\SerializableClosure;
use ReflectionParameter;

test('it can extract the route action signature parameters', function () {
    $callable = function (SignatureParametersUser $user) {
        return $user;
    };

    $action = ['uses' => serialize(
        new SerializableClosure($callable)
    )];

    $parameters = RouteSignatureParameters::fromAction($action);

    expect($parameters)->toContainOnlyInstancesOf(ReflectionParameter::class);
    expect($parameters[0]->getName())->toBe('user');
});

class SignatureParametersUser extends Model
{
    //
}
