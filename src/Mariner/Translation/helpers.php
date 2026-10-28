<?php

use Mariner\Contracts\Translation\Translator;

if (! function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return ($key is null ? null : array|string)
     */
    function __($key = null, $replace = [], $locale = null): string|array|null
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace, $locale);
    }
}

if (! function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return ($key is null ? \Mariner\Contracts\Translation\Translator : array|string)
     */
    function trans($key = null, $replace = [], $locale = null): Translator|array|string
    {
        if (is_null($key)) {
            return app('translator');
        }

        return app('translator')->get($key, $replace, $locale);
    }
}

if (! function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param  string  $key
     * @param  \Countable|int|float|array  $number
     * @param  string|null  $locale
     */
    function trans_choice($key, $number, array $replace = [], $locale = null): string
    {
        return app('translator')->choice($key, $number, $replace, $locale);
    }
}

if (! function_exists('lang_path')) {
    /**
     * Get the path to the language folder.
     *
     * @param  string  $path
     */
    function lang_path($path = ''): string
    {
        return app()->joinPaths(app('path.lang'), $path);
    }
}
