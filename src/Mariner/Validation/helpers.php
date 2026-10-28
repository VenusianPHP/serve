<?php

use Mariner\Contracts\Validation\Factory as ValidationFactory;
use Mariner\Contracts\Validation\Validator as ValidatorContract;
use Mariner\Routing\Router;

if (! function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @return ($data is null ? \Mariner\Contracts\Validation\Factory : \Mariner\Contracts\Validation\Validator)
     */
    function validator(?array $data = null, array $rules = [], array $messages = [], array $attributes = []): ValidatorContract|ValidationFactory
    {
        $factory = app(ValidationFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data ?? [], $rules, $messages, $attributes);
    }
}

if (! function_exists('precognitive')) {
    /**
     * Handle a Precognition controller hook.
     *
     * @param  null|callable  $callable
     * @return mixed
     */
    function precognitive($callable = null)
    {
        $callable ??= function () {
            //
        };

        $payload = $callable(function ($default, $precognition = null) {
            $response = request()->isPrecognitive()
                ? ($precognition ?? $default)
                : $default;

            abort(Router::toResponse(request(), value($response)));
        });

        if (request()->isPrecognitive()) {
            abort(204, headers: ['Precognition-Success' => 'true']);
        }

        return $payload;
    }
}
