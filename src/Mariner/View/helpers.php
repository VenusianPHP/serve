<?php

use Mariner\Contracts\View\Factory as ViewFactory;
use Mariner\Contracts\View\View as ViewContract;
use Voyager\NutsAndBolts\HtmlString;

if (! function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string|null  $view
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return ($view is null ? \Mariner\Contracts\View\Factory : \Mariner\Contracts\View\View)
     */
    function view($view = null, $data = [], $mergeData = []): ViewFactory|ViewContract
    {
        $factory = app(ViewFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

if (! function_exists('method_field')) {
    /**
     * Generate a form field to spoof the HTTP verb used by forms.
     *
     * @param  string  $method
     */
    function method_field($method): HtmlString
    {
        return new HtmlString('<input type="hidden" name="_method" value="'.$method.'">');
    }
}
