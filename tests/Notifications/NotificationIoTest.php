<?php

use Mariner\Notifications\ChannelManager;
use Mariner\Notifications\DatabaseNotification;
use Venusian\Serve\Tests\Fixtures\Member;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Venusian\Serve\Tests\Fixtures\Welcome;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\Schema\Blueprint;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-notify-'.bin2hex(random_bytes(4));
    mkdir($this->dir);

    // A database file both this process and the pool's workers open.
    touch($this->dir.'/notify.sqlite');
    $this->app['config']->set('database.connections.notify', ['driver' => 'sqlite', 'database' => $this->dir.'/notify.sqlite', 'prefix' => '']);
    $this->app['config']->set('database.default', 'notify');

    $schema = $this->app['db']->connection('notify')->getSchemaBuilder();
    $schema->create('members', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    $schema->create('notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    $this->ada = Member::create(['name' => 'ada']);
    $this->grace = Member::create(['name' => 'grace']);

    $this->inserts = 0;
    $this->app['db']->connection('notify')->listen(function ($query) {
        $this->inserts += str_starts_with(strtolower($query->sql), 'insert') ? 1 : 0;
    });

    $this->manager = $this->app[ChannelManager::class];
});

afterEach(function () {
    DatabaseNotification::flushEventListeners();
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

it('keeps blocking sends exactly as they were', function () {
    $this->manager->sendNow($this->ada, new Welcome);

    expect($this->inserts)->toBe(1)
        ->and($this->ada->notifications()->first()->data)->toBe(['greeting' => 'hello']);
});

// Thread workers need ext-parallel; without it, null picks process workers.
dataset('pools', array_merge([null, 'process'], extension_loaded('parallel') ? ['thread'] : []));

it('answers each send with a promise, saving the notification in a worker with its events here', function (?string $pool) {
    $events = [];
    DatabaseNotification::creating(function () use (&$events) {
        $events[] = 'creating';
    });
    DatabaseNotification::created(function ($model) use (&$events) {
        $events[] = 'created:'.($model->exists ? 'exists' : 'new');
    });

    $promise = $this->manager->async($pool)->sendNow($this->ada, new Welcome('hi'));

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($this->app['event-loop']->await($promise))->toBeNull()
        ->and($this->inserts)->toBe(0)
        ->and($events)->toBe(['creating', 'created:exists'])
        ->and($this->ada->notifications()->first())
        ->type->toBe(Welcome::class)
        ->data->toBe(['greeting' => 'hi'])
        ->created_at->not->toBeNull();
})->with('pools');

it('sends in the locale it is given, leaving the shared manager untouched', function () {
    $this->app['event-loop']->await($this->manager->async()->locale('fr')->sendNow($this->ada, new class extends Welcome {
        public function toArray($notifiable)
        {
            return ['locale' => app('translator')->getLocale()];
        }
    }));

    expect($this->ada->notifications()->first()->data)->toBe(['locale' => 'fr']);
});

it('honours a creating listener that cancels the save', function () {
    DatabaseNotification::creating(fn () => false);

    $this->app['event-loop']->await($this->manager->async()->sendNow($this->ada, new Welcome));

    expect($this->ada->notifications()->count())->toBe(0)
        ->and($this->inserts)->toBe(0);
});

it('leaves the shared manager\'s database channel blocking', function () {
    $this->app['event-loop']->await($this->manager->async()->sendNow($this->ada, new Welcome));
    $this->manager->sendNow($this->grace, new Welcome);

    expect($this->inserts)->toBe(1)
        ->and(DatabaseNotification::count())->toBe(2);
});

it('lets two loop tasks overlap their async sends', function () {
    $loop = $this->app['event-loop'];
    $events = [];
    $send = function (Member $member) use ($loop, &$events) {
        $async = $this->manager->async();

        return $loop->async(function () use ($async, $member, $loop, &$events) {
            $events[] = "{$member->name}:start";
            $loop->await($async->sendNow($member, new Welcome));
            $events[] = "{$member->name}:done";
        });
    };

    $a = $send($this->ada);
    $b = $send($this->grace);
    $loop->await($a);
    $loop->await($b);

    expect(array_search('grace:start', $events))->toBeLessThan(array_search('ada:done', $events))
        ->and(DatabaseNotification::count())->toBe(2);
});
