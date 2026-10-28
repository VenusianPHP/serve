<?php

namespace Mariner\Contracts\Auth;

interface Factory
{
    /**
     * Get a guard instance by name.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Mariner\Contracts\Auth\Guard|\Mariner\Contracts\Auth\StatefulGuard
     */
    public function guard($name = null);

    /**
     * Set the default guard the factory should serve.
     *
     * @param  \UnitEnum|string|null  $name
     * @return void
     */
    public function shouldUse($name);
}
