<?php

namespace Mariner\Contracts\NutsAndBolts;

use ArrayAccess;
use IteratorAggregate;
use Voyager\Contracts\NutsAndBolts\Arrayable;

interface ValidatedData extends Arrayable, ArrayAccess, IteratorAggregate
{
    //
}
