<?php

namespace Mariner\Validation\Rules;

use Mariner\Contracts\Validation\ParameterizedRule;
use Mariner\Validation\Rules\Concerns\FormatsParameters;

class RequiredIfDeclined implements ParameterizedRule
{
    use FormatsParameters;

    /**
     * Create a new required_if_declined rule instance.
     */
    public function __construct(protected string $field)
    {
    }

    /**
     * Get the rule name followed by its parameters.
     */
    public function toArray(): array
    {
        return ['required_if_declined', $this->field];
    }
}
