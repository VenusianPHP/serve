<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Exception;
use Voyager\Database\Instrument\ModelNotFoundException;
use Mariner\Validation\FormRequest;
use Mariner\Http\Middleware\HandlePrecognitiveRequests;
use Mariner\Validation\ValidatesRequests;
use Mariner\Http\Request;
use Mariner\Routing\CallableDispatcher;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;
use Mariner\Routing\ControllerDispatcher;
use Mariner\Session\Middleware\StartSession;
use Mariner\NutsAndBolts\Facades\Gate;
use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\Validator;

test('it doesnt invoke controller method by default', function () {
    Route::get('test-route', [PrecognitionTestController::class, 'methodThatFails'])
        ->middleware(HandlePrecognitiveRequests::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertNoContent();
    $response->assertHeader('Precognition-Success', 'true');
    expect($this->app['ClassWasInstantiated'])->toBeTrue();
});

test('it doesnt invoke callable controller by default', function () {
    $resolved = false;
    Route::get('test-route', fn (ClassThatBindsOnInstantiation $foo) => fail())
        ->middleware(HandlePrecognitiveRequests::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertNoContent();
    $response->assertHeader('Precognition-Success', 'true');
    expect($this->app['ClassWasInstantiated'])->toBeTrue();
});

test('it can check precognitive state on the request', function () {
    Route::get('test-route', fn () => fail())
        ->middleware(PrecognitionInvokingController::class);

    $this->get('test-route');
    expect(request()->attributes->get('precognitive'))->toBeNull();
    expect(request()->isPrecognitive())->toBeFalse();

    $this->get('test-route', ['Precognition' => 'true']);
    expect(request()->attributes->get('precognitive'))->toBeTrue();
    expect(request()->isPrecognitive())->toBeTrue();
});

test('it returns the empty response when not bailing', function () {
    Route::get('test-route', function () {
        precognitive(function () {
            //
        });

        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertNoContent();
    $response->assertHeader('Precognition-Success', 'true');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Vary', 'Precognition');
});

test('it can exclude validation rules when precognitive with form request', function () {
    Route::post('test-route', fn (PrecognitionTestRequest $request) => fail())
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'required_integer' => [
            'The required integer field must be an integer.',
        ],
    ]);
});

test('it runs excluded rules when not precognitive for form request', function () {
    Route::post('test-route', fn (PrecognitionTestRequest $request) => fail())
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'required_integer' => [
            'The required integer field must be an integer.',
        ],
        'required_integer_when_not_precognitive' => [
            'The required integer when not precognitive field must be an integer.',
        ],
    ]);
});

test('client can specify input to validate', function () {
    Route::post('test-route', fn (PrecognitionTestRequest $request) => fail())
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'optional_integer_1' => 'foo',
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('client can specify no inputs to validate', function () {
    Route::post('test-route', fn (PrecognitionTestRequest $request) => fail())
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'foo',
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => '',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition-Success', 'true');
});

test('it can return values from precognition closure', function () {
    Route::get('test-route', function () {
        [$first, $second, $third] = precognitive(function () {
            return ['expected', 'values', 'passed'];
        });

        return [
            'first' => $first,
            'second' => $second,
            'third' => $third,
        ];
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route');

    $response->assertOk();
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertExactJson([
        'first' => 'expected',
        'second' => 'values',
        'third' => 'passed',
    ]);
});

test('it can bail with response during normal request', function () {
    Route::get('test-route', function () {
        precognitive(function ($bail) {
            $bail(response()->json(['expected' => 'response']));

            fail();
        });

        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route');

    $response->assertOk();
    $response->assertJson(['expected' => 'response']);
    $response->assertHeaderMissing('Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it does not match wildcard across segments', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereUsersAreValidated'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'users' => [
            ['name' => 123, 'email' => 'invalid'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'users.*',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition-Success', 'true');
});

test('spaces are important in validation filter logic for json requests', function () {
    Route::post('test-route', fn (PrecognitionTestRequest $request) => fail())
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        ' input with spaces ' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => ' input with spaces ',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        ' input with spaces ' => [
            'The input with spaces field must be an integer.',
        ],
    ]);
});

test('vary header is applied to non precognition responses', function () {
    Route::get('test-route', fn () => 'ok')
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route');

    $response->assertOk();
    expect($response->content())->toBe('ok');
    $response->assertHeader('Vary', 'Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it stops execution after successful validation with validation filtering and form request', function () {
    Route::post('test-route', function (PrecognitionTestRequest $request, ClassThatBindsOnInstantiation $foo) {
        fail();
    })->middleware(PrecognitionInvokingController::class);
    $this->app->registerInstance('ClassWasInstantiated', false);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    expect($this->app['ClassWasInstantiated'])->toBeFalse();
    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it stops execution after failed validation with nested validation filtering using form request', function () {
    Route::post('test-route', function (NestedPrecognitionTestRequest $request) {
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'nested' => [
            ['namsse' => 'sdsd'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'nested,nested.0.name',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The nested.0.name field is required.',
        'errors' => [
            'nested' => [
                [
                    'name' => [
                        'The nested.0.name field is required.',
                    ],
                ],
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it can pass validation for escaped dots after filtering with precognition', function () {
    Route::post('test-route', function (PrecognitionRequestWithEscapedDots $request) {
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'escaped.dot' => 'value',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'escaped\.dot',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it can filter rules with escaped dots using form request', function () {
    Route::post('test-route', function (PrecognitionRequestWithEscapedDots $request) {
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        //
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'escaped\.dot',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The escaped.dot field is required.',
        'errors' => [
            'escaped.dot' => [
                'The escaped.dot field is required.',
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it stops execution after successful validation with validation filtering and controller validate', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it stops execution after successful validation with validation filtering and controller validate with bag', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWithBag'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it stops execution after successful validation with validation filtering and controller validate with', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWith'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it stops execution after successful validation with validation filtering and controller validate with passing validator', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWithPassingValidator'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it stops execution after successful validation with validation filtering and request validate', function () {
    Route::post('test-route', function (Request $request) {
        precognitive(function ($bail) use ($request) {
            $request->validate([
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    })
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'optional_integer_1' => 1,
        'optional_integer_2' => 'foo',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1',
    ]);

    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Precognition-Success', 'true');
});

test('it does not set last url', function () {
    Route::get('expected-route-1', fn () => 'ok')->middleware(StartSession::class);
    Route::get('expected-route-2', fn () => 'ok')->middleware(StartSession::class);
    Route::get('precognition-route', fn () => 'ok')->middleware([StartSession::class, HandlePrecognitiveRequests::class]);

    $this->app->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $response = $this->get('expected-route-1');
    $response->assertOk();
    expect(session()->previousUrl())->toBe('http://localhost/expected-route-1');

    $this->app->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $response = $this->get('precognition-route', ['Precognition' => 'true']);
    $response->assertNoContent();
    expect(session()->previousUrl())->toBe('http://localhost/expected-route-1');

    $this->app->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $response = $this->get('expected-route-2');
    $response->assertOk();
    expect(session()->previousUrl())->toBe('http://localhost/expected-route-2');
});

test('it appends vary header to symfony response', function () {
    Route::get('test-route', function () {
        return response()->streamDownload(function () {
            echo 'foo';
        }, null, ['Expected' => 'Header']);
    })->middleware(HandlePrecognitiveRequests::class);

    $response = $this->get('test-route');
    $response->assertOk();
    $response->assertHeader('Expected', 'Header');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it appends precognition header to symfony response', function () {
    Route::get('test-route', function () {
        //
    })->middleware([
        HandlePrecognitiveRequests::class,
        MiddlewareReturningSymfonyResponse::class,
    ]);

    $response = $this->get('test-route', ['Precognition' => 'true']);
    $response->assertOk();
    $response->assertHeader('Expected', 'Header');
    $response->assertHeader('Precognition', 'true');
});

test('it can no content while also not being precognitive', function () {
    Route::get('test-route', function () {
        //
    })->middleware([HandlePrecognitiveRequests::class, MiddlewareThatReturnsNoContent::class]);

    $response = $this->get('test-route', ['Precognition' => 'true']);
    $response->assertNoContent();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success', 'true');
});

test('it can bail during precognition request', function () {
    Route::get('test-route', function () {
        precognitive(function ($bail) {
            $bail(response()->json(['expected' => 'response']));
            fail();
        });
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertOk();
    $response->assertJson(['expected' => 'response']);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it applies headers when exception thrown in precognition', function () {
    Route::get('test-route', function () {
        precognitive(function () {
            throw new ModelNotFoundException();
        });
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertNotFound();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Vary', 'Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it applies headers when flow control exception is thrown', function () {
    // Check with Authorize middleware first...
    Gate::define('alwaysDeny', fn () => false);
    Route::get('test-route-before', fn () => fail())
        ->middleware(['can:alwaysDeny', HandlePrecognitiveRequests::class]);

    $response = $this->get('test-route-before', ['Precognition' => 'true']);

    $response->assertForbidden();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Vary', 'Precognition');
    $response->assertHeaderMissing('Precognition-Success');

    // Check with Authorize middleware last...
    Route::get('test-route-after', fn () => fail())
        ->middleware([HandlePrecognitiveRequests::class, 'can:alwaysDeny']);

    $response = $this->get('test-route-after', ['Precognition' => 'true']);

    $response->assertForbidden();
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Vary', 'Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('arbitrary bail response is parsed to response', function () {
    Route::get('test-route', function () {
        precognitive(function ($bail) {
            $bail(['expected' => 'response']);

            fail();
        });
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->get('test-route');
    $response->assertJson(['expected' => 'response']);
    $response->assertHeaderMissing('Precognition');

    $response = $this->get('test-route', ['Precognition' => 'true']);
    $response->assertJson(['expected' => 'response']);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeader('Vary', 'Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('client can specify inputs to validate when using controller validate', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionValidatesViaControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'integer',
        'optional_integer_2' => 'integer',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('client can specify inputs to validate when using controller validate with bag', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionValidatesViaControllerValidateWithBag'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'integer',
        'optional_integer_2' => 'integer',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('client can specify inputs to validate when using request validate', function () {
    Route::post('test-route', function (Request $request) {
        precognitive(function () use ($request) {
            $request->validate([
                'required_integer' => 'required|integer',
                ...! $request->isPrecognitive() ? ['required_integer_when_not_precognitive' => 'required|integer'] : [],
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            fail();
        });
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'integer',
        'optional_integer_2' => 'integer',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('client can specify inputs to validate when using request validate with bag', function () {
    Route::post('test-route', function (Request $request) {
        precognitive(function () use ($request) {
            $request->validateWithBag('custom-bag', [
                'required_integer' => 'required|integer',
                ! $request->isPrecognitive() ? ['required_integer_when_not_precognitive' => 'required|integer'] : [],
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            fail();
        });

        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'integer',
        'optional_integer_2' => 'integer',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('client can specify inputs to validate when using controller validate with passing array of rules', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionValidatesViaControllerValidateWith'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        // 'required_integer' => 'foo',
        'required_integer_when_not_precognitive' => 'foo',
        'optional_integer_1' => 'integer',
        'optional_integer_2' => 'integer',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'optional_integer_1,optional_integer_2',
    ]);

    $response->assertStatus(422);
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'optional_integer_1' => [
            'The optional integer 1 field must be an integer.',
        ],
        'optional_integer_2' => [
            'The optional integer 2 field must be an integer.',
        ],
    ]);
});

test('it can validate array with wildcard', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereArrayRulesAreValidateViaControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'raw_array' => [123, 'someString'],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'raw_array.*',
    ]);

    $response->assertUnprocessable();
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'raw_array.0' => [
            'The raw_array.0 field must be a string.',
        ],
    ]);
});

test('it can validate nested array with wildcard', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereArrayRulesAreValidateViaControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'nested_array' => [
            ['name' => 123],
            ['name' => 'someString'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'nested_array.*.name',
    ]);

    $response->assertUnprocessable();
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'nested_array.0.name' => [
            'The nested_array.0.name field must be a string.',
        ],
    ]);
});

test('it can validate nested object fields with wildcard', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereProfileIsValidated'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'profile' => ['username' => 123, 'email' => 'invalid'],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'profile.*',
    ]);

    $response->assertUnprocessable();
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonPath('errors', [
        'profile.username' => ['The profile.username field must be a string.'],
        'profile.email' => ['The profile.email field must be a valid email address.'],
    ]);
});

test('it does not match wildcard against other root keys', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWithMultipleRootKeys'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'user' => ['name' => 123],
        'email' => 'not-an-email',
        'name' => 123,
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'user.*',
    ]);

    $response->assertUnprocessable();
    $response->assertHeaderMissing('Precognition-Success');
    $response->assertJsonMissing(['errors' => ['email' => []]]);
    $response->assertJsonMissing(['errors' => ['name' => []]]);
    $response->assertJsonPath('errors', [
        'user.name' => ['The user.name field must be a string.'],
    ]);
});

test('it can validate all nested fields with double wildcard', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereUsersAreValidated'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'users' => [
            ['name' => 123, 'email' => 'invalid'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'users,users.*.*',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors', [
        'users.0.name' => ['The users.0.name field must be a string.'],
        'users.0.email' => ['The users.0.email field must be a valid email address.'],
    ]);
});

test('it can validate specific index without wildcard', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereUsersAreValidated'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'users' => [
            ['name' => 123, 'email' => 'invalid'],
            ['name' => 456, 'email' => 'also-invalid'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'users.1.email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonMissing(['errors' => ['users.0.email' => []]]);
    $response->assertJsonPath('errors', [
        'users.1.email' => ['The users.1.email field must be a valid email address.'],
    ]);
});

test('it appends an additional vary header instead of replacing any existing vary headers', function () {
    Route::get('test-route', function () {
        precognitive(function ($bail) {
            $bail(response('expected')->header('Vary', 'Foo'));
            fail();
        });
        fail();
    })->middleware([PrecognitionInvokingController::class]);

    $response = $this->get('test-route', ['Precognition' => 'true']);

    $response->assertHeader('Vary', 'Foo, Precognition');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it stops execution after failed validation with nested validation filtering using request validate', function () {
    Route::post('test-route', function (Request $request) {
        $request->validate([
            'nested' => ['required', 'array', 'min:1'],
            'nested.*.name' => ['required', 'string'],
        ]);
        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'nested' => [
            ['namsse' => 'sdsd'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'nested,nested.0.name',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The nested.0.name field is required.',
        'errors' => [
            'nested' => [
                [
                    'name' => [
                        'The nested.0.name field is required.',
                    ],
                ],
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it stops execution after failed validation with nested validation filtering using controller validate', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereNestedRulesAreValidatedViaControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'nested' => [
            ['namsse' => 'sdsd'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'nested,nested.0.name',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The nested.0.name field is required.',
        'errors' => [
            'nested' => [
                [
                    'name' => [
                        'The nested.0.name field is required.',
                    ],
                ],
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it stops execution after failed validation with nested validation filtering using controller validate with', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereNestedRulesAreValidatedViaControllerValidateWith'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        'nested' => [
            ['namsse' => 'sdsd'],
        ],
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'nested,nested.0.name',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The nested.0.name field is required.',
        'errors' => [
            'nested' => [
                [
                    'name' => [
                        'The nested.0.name field is required.',
                    ],
                ],
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it can filter rules with escaped dots when using request validate', function () {
    Route::post('test-route', function (Request $request) {
        $request->validate([
            'escaped\.dot' => 'required',
        ]);

        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        //
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'escaped\.dot',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The escaped.dot field is required.',
        'errors' => [
            'escaped.dot' => [
                'The escaped.dot field is required.',
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it can filter rules with escaped dots when using controller validate', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereEscapedDotRuleIsValidatedViaControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        //
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'escaped\.dot',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The escaped.dot field is required.',
        'errors' => [
            'escaped.dot' => [
                'The escaped.dot field is required.',
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it can filter rules with escaped dots when using controller validate with', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWhereEscapedDotRuleIsValidatedViaControllerValidateWith'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->postJson('test-route', [
        //
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'escaped\.dot',
    ]);

    $response->assertStatus(422);
    $response->assertExactJson([
        'message' => 'The escaped.dot field is required.',
        'errors' => [
            'escaped.dot' => [
                'The escaped.dot field is required.',
            ],
        ],
    ]);
    $response->assertHeader('Precognition', 'true');
});

test('it continues execution after successful validation without validation filtering and form request', function () {
    Route::post('test-route', function (PrecognitionTestRequest $request, ClassThatBindsOnInstantiation $foo) {
        precognitive(function ($bail) {
            $bail(response('expected response'));
            fail();
        });
        fail();
    })->middleware(PrecognitionInvokingController::class);
    $this->app->registerInstance('ClassWasInstantiated', false);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    expect($this->app['ClassWasInstantiated'])->toBeTrue();
    $response->assertOk();
    expect($response->content())->toBe('expected response');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it continues execution after successful validation without validation filtering and controller validate', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidate'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertOk();
    expect($response->content())->toBe('Post-validation code was executed.');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it continues execution after successful validation without validation filtering and controller validate with bag', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWithBag'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertOk();
    expect($response->content())->toBe('Post-validation code was executed.');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it continues execution after successful validation without validation filtering and controller validate with xxxx', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWith'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertOk();
    expect($response->content())->toBe('Post-validation code was executed.');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it continues execution after successful validation without validation filtering and controller validate with passing validator', function () {
    Route::post('test-route', [PrecognitionTestController::class, 'methodWherePredictionReturnsResponseWithControllerValidateWithPassingValidator'])
        ->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertOk();
    expect($response->content())->toBe('Post-validation code was executed.');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it continues execution after successful validation without validation filtering and request validate', function () {
    Route::post('test-route', function (Request $request) {
        precognitive(function ($bail) use ($request) {
            $request->validate([
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    })->middleware(PrecognitionInvokingController::class);

    $response = $this->post('test-route', [
        'required_integer' => 1,
    ], [
        'Precognition' => 'true',
    ]);

    $response->assertOk();
    expect($response->content())->toBe('Post-validation code was executed.');
    $response->assertHeader('Precognition', 'true');
    $response->assertHeaderMissing('Precognition-Success');
});

test('it restores original dispatcher bindings after precognitive request', function () {
    Route::get('test-route', fn () => 'ok')
        ->middleware(HandlePrecognitiveRequests::class);

    // Voyager's container has no getBindings(): what the request must leave alone is what the contracts resolve to.
    $callableBefore = $this->app->make(CallableDispatcherContract::class);
    $controllerBefore = $this->app->make(ControllerDispatcherContract::class);

    $response = $this->get('test-route', ['Precognition' => 'true']);
    $response->assertNoContent();

    expect($this->app->make(CallableDispatcherContract::class))->toBeInstanceOf(CallableDispatcher::class);
    expect($this->app->make(ControllerDispatcherContract::class))->toBeInstanceOf(ControllerDispatcher::class);

    expect($this->app->make(CallableDispatcherContract::class) === $callableBefore)
        ->toBe($this->app->isShared(CallableDispatcherContract::class));
    expect($this->app->make(ControllerDispatcherContract::class) === $controllerBefore)
        ->toBe($this->app->isShared(ControllerDispatcherContract::class));
});

function fail()
{
    throw new Exception('This code should not be reached.');
}

class PrecognitionTestController
{
    use ValidatesRequests;

    public function methodWhereEscapedDotRuleIsValidatedViaControllerValidate(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'escaped\.dot' => 'required',
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereEscapedDotRuleIsValidatedViaControllerValidateWith()
    {
        precognitive(function () {
            $this->validateWith([
                'escaped\.dot' => 'required',
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereNestedRulesAreValidatedViaControllerValidate(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'nested' => ['required', 'array', 'min:1'],
                'nested.*.name' => ['required', 'string'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereArrayRulesAreValidateViaControllerValidate(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'nested_array' => ['required', 'array', 'min:1'],
                'nested_array.*.name' => ['required', 'string'],
                'raw_array' => ['required', 'array', 'min:1'],
                'raw_array.*' => ['required', 'string'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereUsersAreValidated(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'users' => ['required', 'array'],
                'users.*.name' => ['required', 'string'],
                'users.*.email' => ['required', 'email'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWithMultipleRootKeys(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'user' => ['required', 'array'],
                'user.name' => ['required', 'string'],
                'email' => ['required', 'email'],
                'name' => ['required', 'string'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereProfileIsValidated(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'profile' => ['required', 'array'],
                'profile.username' => ['required', 'string'],
                'profile.email' => ['required', 'email'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWhereNestedRulesAreValidatedViaControllerValidateWith(Request $request)
    {
        precognitive(function () {
            $this->validateWith([
                'nested' => ['required', 'array', 'min:1'],
                'nested.*.name' => ['required', 'string'],
            ]);

            fail();
        });

        fail();
    }

    public function methodWherePredictionValidatesViaControllerValidate(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validate($request, [
                'required_integer' => 'required|integer',
                ...! $request->isPrecognitive() ? ['required_integer_when_not_precognitive' => 'required|integer'] : [],
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            fail();
        });

        fail();
    }

    public function methodWherePredictionValidatesViaControllerValidateWithBag(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validateWithBag('custom-bag', $request, [
                'required_integer' => 'required|integer',
                ...! $request->isPrecognitive() ? ['required_integer_when_not_precognitive' => 'required|integer'] : [],
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            fail();
        });

        fail();
    }

    public function methodWherePredictionValidatesViaControllerValidateWith(Request $request)
    {
        precognitive(function () use ($request) {
            $this->validateWith([
                'required_integer' => 'required|integer',
                ...! $request->isPrecognitive() ? ['required_integer_when_not_precognitive' => 'required|integer'] : [],
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            fail();
        });

        fail();
    }

    public function methodWherePredictionReturnsResponseWithControllerValidate(Request $request)
    {
        precognitive(function ($bail) use ($request) {
            $this->validate($request, [
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    }

    public function methodWherePredictionReturnsResponseWithControllerValidateWithBag(Request $request)
    {
        precognitive(function ($bail) use ($request) {
            $this->validateWithBag('custom-bag', $request, [
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    }

    public function methodWherePredictionReturnsResponseWithControllerValidateWith(Request $request)
    {
        precognitive(function ($bail) {
            $this->validateWith([
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]);

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    }

    public function methodWherePredictionReturnsResponseWithControllerValidateWithPassingValidator(Request $request)
    {
        precognitive(function ($bail) use ($request) {
            $this->validateWith(Validator::make($request->all(), [
                'required_integer' => 'required|integer',
                'optional_integer_1' => 'integer',
                'optional_integer_2' => 'integer',
            ]));

            $bail(response('Post-validation code was executed.'));

            fail();
        });

        fail();
    }

    public function methodThatFails(ClassThatBindsOnInstantiation $foo)
    {
        fail();
    }
}

class PrecognitionTestRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'required_integer' => 'required|integer',
            'optional_integer_1' => 'integer',
            'optional_integer_2' => 'integer',
            ' input with spaces ' => 'integer',
        ];

        if (! $this->isPrecognitive()) {
            $rules['required_integer_when_not_precognitive'] = 'required|integer';
        }

        return $rules;
    }
}

class NestedPrecognitionTestRequest extends FormRequest
{
    public function rules()
    {
        return [
            'nested' => ['required', 'array', 'min:1'],
            'nested.*.name' => ['required', 'string'],
        ];
    }
}

class PrecognitionRequestWithEscapedDots extends FormRequest
{
    public function rules()
    {
        return [
            'escaped\.dot' => ['required'],
        ];
    }
}

class ClassThatBindsOnInstantiation
{
    public function __construct()
    {
        app()->registerInstance('ClassWasInstantiated', true);
    }
}

class PrecognitionInvokingController extends HandlePrecognitiveRequests
{
    protected function prepareForPrecognition($request)
    {
        parent::prepareForPrecognition($request);

        $this->dispatchUsing(
            $request,
            callable: fn ($app) => new CallableDispatcher($app),
            controller: fn ($app) => new ControllerDispatcher($app),
        );
    }
}

class MiddlewareReturningSymfonyResponse
{
    public function handle($request, $next)
    {
        return response()->streamDownload(function () {
            //
        }, null, ['Expected' => 'Header']);
    }
}

class MiddlewareThatReturnsNoContent
{
    public function handle()
    {
        return response()->noContent();
    }
}
