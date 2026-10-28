<?php

namespace Mariner\Validation\Rules;

use Mariner\Contracts\Validation\ParameterizedRule;
use Mariner\Validation\Rules\Concerns\FormatsParameters;

class ExcludeWithout implements ParameterizedRule
{
    use FormatsParameters;

    protected array $fields;

    /**
     * Create a new exclude_without rule instance.
     */
    public function __construct(array|string $fields)
    {
        $this->fields = is_array($fields) ? $fields : func_get_args();
    }

    /**
     * Get the rule name followed by its parameters.
     */
    public function toArray(): array
    {
        return ['exclude_without', ...array_values($this->fields)];
    }
}
