<?php

namespace Mariner\Auth\Passwords;

use Mariner\Auth\Notifications\ResetPassword as ResetPasswordNotification;

trait CanResetPassword
{
    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        // @phpstan-ignore method.notFound (the app's user adds Notifiable, as with Laravel's base User)
        $this->notify(new ResetPasswordNotification($token));
    }
}
