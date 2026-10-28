<?php

namespace Mariner\Contracts\Auth;

interface PasswordBrokerFactory
{
    /**
     * Get a password broker instance by name.
     *
     * @param  string|null  $name
     * @return \Mariner\Contracts\Auth\PasswordBroker
     */
    public function broker($name = null);
}
