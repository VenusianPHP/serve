<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Mailable;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('webapp.locale', 'en');

    $app['view']->addLocation(__DIR__.'/Fixtures');

    $app['translator']->setLoaded([
        '*' => [
            '*' => [
                'en' => ['nom' => 'name'],
                'es' => ['nom' => 'nombre'],
            ],
        ],
    ]);
});

test('mailable renders in default locale', function () {
    $mail = new RenderedTestMail;

    expect($mail->render())->toContain('name');
});

test('mailable renders in selected locale', function () {
    $mail = (new RenderedTestMail)->locale('es');

    expect($mail->render())->toContain('nombre');
});

test('mailable renders in app selected locale', function () {
    $this->app["translator"]->setLocale('es');

    $mail = new RenderedTestMail;

    expect($mail->render())->toContain('nombre');
});

class RenderedTestMail extends Mailable
{
    public function build()
    {
        return $this->view('view');
    }
}
