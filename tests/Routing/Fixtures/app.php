<?php

namespace Venusian\Serve\Tests\Routing\Fixtures;

use Voyager\Core\RenderedInstance;

if (! class_exists(AppCache::class)) {
    class AppCache
    {
        public static $app;
    }
}

if (isset($refresh)) {
    return AppCache::$app = Application::configure(basePath: __DIR__)->create();
} else {
    return AppCache::$app ??= Application::configure(basePath: __DIR__)->create();
}
