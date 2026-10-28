<?php

namespace Mariner\Translation;

/**
 * The locale chosen during the current request.
 *
 * Registered with scoped(), so App::setLocale()/Localizable in one request (or concurrent request
 * fiber) never changes the locale another one sees. Null means "use the translator's default",
 * which comes from config('webapp.locale') and is never rewritten while serving.
 */
final class LocaleState
{
    public ?string $locale = null;
}
