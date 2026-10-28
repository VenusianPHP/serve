<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Events\Registered;
use Mariner\Auth\Listeners\SendEmailVerificationNotification;
use Mariner\Contracts\Auth\MustVerifyEmail;

test('will executed', function () {
    $user = unverifiedUser();

    $listener = new SendEmailVerificationNotification;

    $listener->handle(new Registered($user));

    expect($user->notificationSent)->toBeTrue();
});

test('user is not instance of must verify email', function () {
    // Has the verification methods but doesn't implement MustVerifyEmail.
    $user = new class
    {
        public $notificationSent = false;

        public function hasVerifiedEmail()
        {
            return false;
        }

        public function sendEmailVerificationNotification()
        {
            $this->notificationSent = true;
        }
    };

    $listener = new SendEmailVerificationNotification;

    $listener->handle(new Registered($user));

    expect($user->notificationSent)->toBeFalse();
});

test('has verified email as true', function () {
    $user = unverifiedUser();
    $user->verified = true;

    $listener = new SendEmailVerificationNotification;

    $listener->handle(new Registered($user));

    expect($user->notificationSent)->toBeFalse();
});

function unverifiedUser()
{
    return new class implements MustVerifyEmail
    {
        public $verified = false;

        public $notificationSent = false;

        public function hasVerifiedEmail()
        {
            return $this->verified;
        }

        public function markEmailAsVerified()
        {
            $this->verified = true;
        }

        public function markEmailAsUnverified()
        {
            $this->verified = false;
        }

        public function sendEmailVerificationNotification()
        {
            $this->notificationSent = true;
        }

        public function getEmailForVerification()
        {
            return 'test@example.com';
        }
    };
}
