<?php

namespace Mariner\NutsAndBolts\Traits;

use Voyager\Vessel\ControlPanel;

trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @template TReturn
     *
     * @param  string  $locale
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withLocale($locale, $callback)
    {
        if (! $locale) {
            return $callback();
        }

        $translator = ControlPanel::getInstance()->make('translator');

        $original = $translator->getLocale();

        try {
            $translator->setLocale($locale);

            return $callback();
        } finally {
            $translator->setLocale($original);
        }
    }
}
