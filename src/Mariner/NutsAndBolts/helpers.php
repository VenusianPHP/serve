<?php

if (! function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param  string  $path
     */
    function public_path($path = ''): string
    {
        return app()->joinPaths(app('path.public'), $path);
    }
}

if (! function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     *
     * @param  string  $path
     */
    function resource_path($path = ''): string
    {
        return app()->joinPaths(app('path.resources'), $path);
    }
}
