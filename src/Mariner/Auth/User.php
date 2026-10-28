<?php

namespace Mariner\Auth;

use Mariner\Auth\Authenticatable;
use Mariner\Auth\MustVerifyEmail;
use Mariner\Auth\Passwords\CanResetPassword;
use Mariner\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Mariner\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Mariner\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Voyager\Database\Instrument\Model;
use Mariner\Auth\Access\Authorizable;

/**
 * @property string $email
 * @property \DateTimeInterface|null $email_verified_at
 */
class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;
}
