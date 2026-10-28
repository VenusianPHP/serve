<?php

namespace Venusian\Serve\Tests\Session;

use Voyager\Cache\CacheManager;
use Voyager\Config\Repository as Config;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Redis\Factory as RedisFactory;
use Mariner\Session\SessionManager;
use Mockery;

test('set default driver accepts backed enum', function () {
    $app = new ControlPanel;
    $app->registerSingleton('config', fn () => new Config(['session' => ['driver' => 'array']]));

    $manager = new SessionManager($app);
    $manager->setDefaultDriver(SessionDriverName::Array);

    expect($app['config']['session.driver'])->toBe('array');
});

test('redis driver uses configured session prefix', function () {
    $app = new ControlPanel;
    $app->registerSingleton('config', fn () => new Config([
        'session' => ['driver' => 'redis', 'lifetime' => 120, 'prefix' => 'some_custom_prefix'],
        'cache' => ['prefix' => 'cache_prefix', 'stores' => ['redis' => ['driver' => 'redis']]],
    ]));
    $app->registerSingleton('cache', fn ($app) => new CacheManager($app));
    $app->registerInstance('redis', Mockery::mock(RedisFactory::class));

    $manager = new SessionManager($app);

    expect($manager->driver()->getHandler()->getCache()->getStore()->getPrefix())->toBe('some_custom_prefix');
});

test('redis driver falls back to cache prefix when no session prefix', function () {
    $app = new ControlPanel;
    $app->registerSingleton('config', fn () => new Config([
        'session' => ['driver' => 'redis', 'lifetime' => 120],
        'cache' => ['prefix' => 'cache_prefix', 'stores' => ['redis' => ['driver' => 'redis']]],
    ]));
    $app->registerSingleton('cache', fn ($app) => new CacheManager($app));
    $app->registerInstance('redis', Mockery::mock(RedisFactory::class));

    $manager = new SessionManager($app);

    expect($manager->driver()->getHandler()->getCache()->getStore()->getPrefix())->toBe('cache_prefix');
});

enum SessionDriverName: string
{
    case Array = 'array';
}
