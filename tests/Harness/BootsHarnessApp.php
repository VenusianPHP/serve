<?php

namespace Venusian\Serve\Tests\Harness;

use Mariner\Http\Request;
use RuntimeException;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Core\RenderedInstance;

/** The fixture app with the routes the harness tests call. */
trait BootsHarnessApp
{
    public function createApplication(): RenderedInstance
    {
        $app = WebApp::boot();
        $app['config']->set('session.driver', 'array');

        // An app's own phpunit.xml sets APP_ENV=testing; the shared fixture does not, so this app does.
        $app->registerInstance('env', 'testing');
        $router = $app['router'];

        $router->middleware('web')->group(function ($router) {
            $router->get('/hello', fn () => 'hello');
            $router->get('/header', fn (Request $request) => $request->header('X-Probe', 'none'));
            $router->get('/cookie', fn (Request $request) => (string) $request->cookie('flavor'));
            $router->get('/session', fn () => (string) session('step', 'none'));
            $router->post('/session', function (Request $request) {
                session(['step' => $request->input('step')]);

                return 'stored';
            });
            $router->get('/me', fn () => (string) (auth()->user()?->getAuthIdentifier() ?? 'guest'));
            $router->get('/boom', fn () => throw new RuntimeException('boom'));
            $router->post('/validate', fn (Request $request) => $request->validate(['name' => 'required']));
            $router->get('/hop', fn () => redirect('/hello'));
        });

        $router->post('/api/echo', fn (Request $request) => ['got' => $request->input('name')])->middleware('api');

        return $app;
    }
}
