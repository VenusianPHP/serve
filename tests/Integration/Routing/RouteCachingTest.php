<?php

namespace Venusian\Serve\Tests\Integration\Routing;



test('wildcard catch all routes', function () {
    $this->defineCacheRoutes(file_get_contents(dirname(__DIR__, 2).'/Routing/Fixtures/wildcard_catch_all_routes.php'));

    $this->get('/foo')->assertSee('Regular route');
    $this->get('/bar')->assertSee('Wildcard route');
});

test('redirect routes', function () {
    $this->defineCacheRoutes(file_get_contents(dirname(__DIR__, 2).'/Routing/Fixtures/redirect_routes.php'));

    $this->post('/foo/1')->assertRedirect('/foo/1/bar');
    $this->get('/foo/1/bar')->assertSee('Redirect response');
    $this->get('/foo/1')->assertRedirect('/foo/1/bar');
});

test('query routes', function () {
    $this->defineCacheRoutes(file_get_contents(dirname(__DIR__, 2).'/Routing/Fixtures/query_routes.php'));

    $this->call('QUERY', '/search?term=laravel', ['filter' => 'framework'])->assertExactJson([
        'method' => 'QUERY',
        'term' => 'laravel',
        'filter' => 'framework',
    ]);
});
