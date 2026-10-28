<?php

namespace Venusian\Serve\Tests\Integration\View;

test('clear view command should remove parallel test directories', function () {
    $path = $this->app['config']->get('view.compiled');

    mkdir($path.'/test_33');
    file_put_contents($path.'/test_33/inner.php', '');
    file_put_contents($path.'/filehash123.php', '');

    $this->artisan('view:clear');

    expect(glob($path.'/*'))->toBeEmpty();
});
