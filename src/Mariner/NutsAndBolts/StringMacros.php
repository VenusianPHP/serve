<?php

namespace Mariner\NutsAndBolts;

use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\Str;

/**
 * The web methods of Laravel's Str, which Venusian's copy leaves out. Stringable::toUri() is
 * registered by the Routing package, which owns the web Uri.
 */
final class StringMacros
{
    public static function register(): void
    {
        Str::macro('trans', fn ($key, $replace = [], $locale = null) => new Stringable(__($key, $replace, $locale)));
    }
}
