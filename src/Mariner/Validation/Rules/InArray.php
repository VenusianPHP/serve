<?php

namespace Mariner\Validation\Rules;

use Mariner\Contracts\Validation\ParameterizedRule;
use Mariner\Validation\Rules\Concerns\FormatsParameters;

class InArray implements ParameterizedRule
{
    use FormatsParameters;

    /**
     * Create a new in_array rule instance.
     */
    public function __construct(protected string $otherField)
    {
    }

    /**
     * Get the rule name followed by its parameters.
     */
    public function toArray(): array
    {
        return ['in_array', $this->otherField];
    }
}
