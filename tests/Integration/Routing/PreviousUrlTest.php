<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Validation\FormRequest;
use Mariner\NutsAndBolts\Facades\Route;

test('previous url without session', function () {
    Route::post('/previous-url', function (DummyFormRequest $request) {
        return 'OK';
    });

    $response = $this->postJson('/previous-url');

    expect($response->status())->toEqual(422);
});

class DummyFormRequest extends FormRequest
{
    public function rules()
    {
        return [
            'foo' => [
                'required',
                'string',
            ],
        ];
    }
}
