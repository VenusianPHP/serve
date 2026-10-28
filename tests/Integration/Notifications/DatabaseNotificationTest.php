<?php

namespace Venusian\Serve\Tests\Integration\Notifications;

use Mariner\Auth\User;
use Mariner\Notifications\Notifiable;
use Mariner\NutsAndBolts\Facades\Notification;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Venusian\Serve\Tests\Notifications\Fixtures\NotificationStub;
use Voyager\Database\Instrument\Casts\AsStringable;
use Voyager\Database\Instrument\Concerns\HasUuids;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

// Testbench's defineDatabaseMigrationsAfterDatabaseRefresh(): the users' id becomes a uuid.
beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->table('users', function (Blueprint $table) {
        $table->uuid('id')->change();
    });
});

test('assert sent to when notifiable has stringable key', function () {
    Notification::fake();

    $user = UuidUserFactoryStub::new()->create();

    $user->notify(new NotificationStub);

    Notification::assertSentTo($user, NotificationStub::class, function ($notification, $channels, $notifiable) use ($user) {
        return $notifiable === $user;
    });
});

class UuidUserFactoryStub extends UserFactory
{
    protected $model = UuidUserStub::class;
}

class UuidUserStub extends User
{
    use HasUuids, Notifiable;

    protected $table = 'users';

    #[\Override]
    public function casts()
    {
        return array_merge(parent::casts(), ['id' => AsStringable::class]);
    }
}
