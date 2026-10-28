<?php

namespace Mariner\Contracts\Validation;

use Mariner\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param  \Mariner\Validation\Validator  $validator
     * @return $this
     */
    public function setValidator(Validator $validator);
}
