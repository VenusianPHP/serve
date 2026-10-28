<?php

namespace Mariner\Validation\Rules;

use Mariner\Contracts\Validation\ParameterizedRule;
use Mariner\Validation\Rules\Concerns\FormatsParameters;

class ExcludeWith implements ParameterizedRule
{
    use FormatsParameters;

    /**
     * Create a new exclude_with rule instance.
     */
    public function __construct(protected string $anotherField)
    {
    }

    /**
     * Get the rule name followed by its parameters.
     */
    public function toArray(): array
    {
        return ['exclude_with', $this->anotherField];
    }
}
