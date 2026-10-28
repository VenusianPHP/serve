<?php

namespace Venusian\Serve\Tests\Notifications\Fixtures\Models;

use Voyager\Database\Instrument\Model;
use Mariner\Notifications\Notifiable;

class NotifiableUser extends Model
{
    use Notifiable;

    public $table = 'users';
    public $timestamps = false;
}
