<?php

namespace Mariner\Validation\Rules;

use Mariner\Contracts\Validation\ParameterizedRule;
use Mariner\Validation\Rules\Concerns\FormatsParameters;

class Timezone implements ParameterizedRule
{
    use FormatsParameters;

    /**
     * Create a new timezone rule instance.
     */
    public function __construct(protected ?array $arguments = null)
    {
    }

    /**
     * Get the rule name followed by its parameters.
     */
    public function toArray(): array
    {
        return ['timezone', ...array_values($this->arguments ?? [])];
    }
}
