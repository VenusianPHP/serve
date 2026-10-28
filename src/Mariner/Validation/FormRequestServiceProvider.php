<?php

namespace Mariner\Validation;

use Mariner\Http\Request;
use Mariner\Contracts\Validation\ValidatesWhenResolved;
use Mariner\Routing\Redirector;
use Voyager\NutsAndBolts\ServiceProvider;

class FormRequestServiceProvider extends ServiceProvider
{
    /**
     * Fill each form request from the current request when it is resolved, then validate it.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerRequestValidation();

        $this->app->afterResolving(ValidatesWhenResolved::class, function ($resolved) {
            $resolved->validateResolved();
        });

        $this->app->resolving(FormRequest::class, function (FormRequest $request, $app) {
            $request = FormRequest::createFrom($app['request'], $request);

            $request->setContainer($app)->setRedirector($app->make(Redirector::class));
        });
    }

    /**
     * Register the "validate" and "validateWithBag" macros on the request.
     */
    protected function registerRequestValidation(): void
    {
        Request::macro('validate', function (array $rules, ...$params) {
            /** @var \Mariner\Http\Request $this the macro runs bound to the request */
            $request = $this;

            return tap(validator($request->all(), $rules, ...$params), function ($validator) use ($request) {
                if ($request->isPrecognitive()) {
                    $validator->after(Precognition::afterValidationHook($request))
                        ->setRules(
                            $request->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders())
                        );
                }
            })->validate();
        });

        Request::macro('validateWithBag', function (string $errorBag, array $rules, ...$params) {
            /** @var \Mariner\Http\Request $this the macro runs bound to the request */
            $request = $this;

            try {
                return $request->validate($rules, ...$params);
            } catch (ValidationException $e) {
                $e->errorBag = $errorBag;

                throw $e;
            }
        });
    }
}
