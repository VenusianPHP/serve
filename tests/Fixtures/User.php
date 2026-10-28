<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\Auth\Authenticatable;
use Mariner\Auth\Passwords\CanResetPassword;
use Mariner\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Mariner\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Voyager\Database\Instrument\Model;

/** An application's user model, on the fixture's 'auth' connection. */
class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    protected $connection = 'auth';

    protected $guarded = [];

    public $timestamps = false;
}
