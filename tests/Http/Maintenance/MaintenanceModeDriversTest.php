<?php

use Mariner\Http\Maintenance\ArrayMaintenanceMode;
use Mariner\Http\Maintenance\CacheBasedMaintenanceMode;
use Mariner\Http\Maintenance\FileBasedMaintenanceMode;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;

// Laravel v13.37.0 tests/Foundation/Foundation{CacheBased,Array}MaintenanceModeTest, plus the file driver.

afterEach(fn () => Mockery::close());

test('cache driver determines whether maintenance mode is active', function () {
    $cache = Mockery::mock(Factory::class, Repository::class);
    $cache->expects('store')->times(2)->with('store-key')->andReturnSelf();
    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');

    $cache->expects('has')->with('key')->andReturnFalse();
    expect($manager->active())->toBeFalse();

    $cache->expects('has')->with('key')->andReturnTrue();
    expect($manager->active())->toBeTrue();
});

test('cache driver retrieves payload from cache', function () {
    $cache = Mockery::mock(Factory::class, Repository::class);
    $cache->expects('store')->with('store-key')->andReturnSelf();
    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');

    $cache->expects('get')->with('key')->andReturn(['payload']);

    expect($manager->data())->toBe(['payload']);
});

test('cache driver stores payload in cache', function () {
    $cache = Mockery::spy(Factory::class, Repository::class);
    $cache->expects('store')->with('store-key')->andReturnSelf();

    (new CacheBasedMaintenanceMode($cache, 'store-key', 'key'))->activate(['payload']);

    $cache->shouldHaveReceived('put')->once()->with('key', ['payload']);
});

test('cache driver removes payload from cache', function () {
    $cache = Mockery::spy(Factory::class, Repository::class);
    $cache->expects('store')->with('store-key')->andReturnSelf();

    (new CacheBasedMaintenanceMode($cache, 'store-key', 'key'))->deactivate();

    $cache->shouldHaveReceived('forget')->once()->with('key');
});

test('array driver determines whether maintenance mode is active', function () {
    $manager = new ArrayMaintenanceMode;
    expect($manager->active())->toBeFalse();

    $manager->activate(['payload']);
    expect($manager->active())->toBeTrue();
});

test('array driver retrieves, stores and removes the payload', function () {
    $manager = new ArrayMaintenanceMode;
    $manager->activate(['payload']);
    expect([$manager->active(), $manager->data()])->toBe([true, ['payload']]);

    $manager->deactivate();
    expect([$manager->active(), $manager->data()])->toBe([false, []]);
});

test('file driver notices up from another process after reporting down', function () {
    $storage = sys_get_temp_dir().'/mariner-down-'.bin2hex(random_bytes(4));
    mkdir($storage.'/framework', 0777, true);
    $driver = new class($storage) extends FileBasedMaintenanceMode {
        public function __construct(private string $storage) {}

        protected function path(): string
        {
            return $this->storage.'/framework/down';
        }
    };

    try {
        // A long-running server asks while down, then `php computer up` deletes the file from another
        // process: the next check, in the same process, must answer "up".
        expect($driver->active())->toBeFalse();
        file_put_contents($storage.'/framework/down', json_encode(['status' => 503]));
        expect($driver->active())->toBeTrue()
            ->and($driver->data())->toBe(['status' => 503]);

        exec('rm '.escapeshellarg($storage.'/framework/down'));
        expect($driver->active())->toBeFalse();
    } finally {
        (new Voyager\Filesystem\Filesystem)->deleteDirectory($storage);
    }
});
