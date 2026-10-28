<?php

namespace Mariner\Contracts\Mail;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param  \UnitEnum|string|null  $name
     * @return \Mariner\Contracts\Mail\Mailer
     */
    public function mailer($name = null);
}
