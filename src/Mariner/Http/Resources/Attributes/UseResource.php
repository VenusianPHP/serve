<?php

namespace Mariner\Http\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class UseResource
{
    /**
     * The resource class to use for the model.
     *
     * @param  string  $class
     */
    public function __construct(public string $class)
    {
        //
    }
}
