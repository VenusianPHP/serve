<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Notifications\Events\NotificationSent;
use Mariner\Notifications\Notifiable;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Venusian\Serve\Tests\Notifications\Fixtures\SentMessageMailNotification;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('sent-message-users'));
afterAll(fn () => IntegrationTestCase::withMigrations());

test('dispatches notification sent', function () {
    $notificationWasSent = false;

    $user = SentMessageUser::create();

    app('signals')->listen(
        NotificationSent::class,
        function (NotificationSent $notification) use (&$notificationWasSent, $user) {
            $notificationWasSent = true;
            /**
             * Confirm that NotificationSent can be serialized/unserialized as
             * will happen if the listener implements ShouldQueue.
             */
            /** @var NotificationSent $afterSerialization */
            $afterSerialization = unserialize(serialize($notification));

            expect($user->is($afterSerialization->notifiable))->toBeTrue();

            expect($afterSerialization->notification)->toEqualCanonicalizing($notification->notification);
        });

    $user->notify(new SentMessageMailNotification());

    expect($notificationWasSent)->toBeTrue();
});

class SentMessageUser extends Model
{
    use Notifiable;

    public $timestamps = false;
}
