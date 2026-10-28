<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\Validation\FormRequest;

/** A form request that needs a name and an email. */
class NameRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required', 'email' => 'required|email'];
    }
}
