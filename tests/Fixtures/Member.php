<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\Notifications\Notifiable;
use Voyager\Database\Instrument\Model;

/** A notifiable model on the default connection, which DatabaseNotification also uses. */
class Member extends Model
{
    use Notifiable;

    protected $table = 'members';

    protected $guarded = [];

    public $timestamps = false;
}
