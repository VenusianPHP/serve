<?php

namespace Mariner\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class FailOnUnknownFields
{
    public function __construct(public bool $value = true)
    {
    }
}
