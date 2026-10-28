<?php

namespace Mariner\Notifications;

trait Notifiable
{
    use HasDatabaseNotifications, RoutesNotifications;
}
