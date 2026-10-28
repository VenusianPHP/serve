<?php

namespace Venusian\Serve\Tests\Integration\Translation;

use Voyager\Filesystem\UploadedFile;
use Mariner\Validation\Rules\File;

beforeEach(function () {
    $app = $this->app;
    $app['translator']->addNamespace('tests', __DIR__.'/Fixtures/lang');
    $app['translator']->addJsonPath(__DIR__.'/Fixtures/lang');

});

// The tests hand their results to each other through $_SERVER; none may see another's.
afterEach(function () {
    unset($_SERVER['__missing_translation_key'], $_SERVER['__missing_translation_key_locale']);
});

test('it can get from locale for json', function () {
    expect($this->app['translator']->get('30 Days'))->toBe('30 Days');

    $this->app["translator"]->setLocale('fr');

    expect($this->app['translator']->get('30 Days'))->toBe('30 jours');
});

test('it can check language exists has from locale for json', function () {
    expect($this->app['translator']->has('1 Day'))->toBeTrue();
    expect($this->app['translator']->hasForLocale('1 Day'))->toBeTrue();
    expect($this->app['translator']->hasForLocale('30 Days'))->toBeTrue();

    $this->app["translator"]->setLocale('fr');

    expect($this->app['translator']->has('1 Day'))->toBeFalse();
    expect($this->app['translator']->hasForLocale('1 Day'))->toBeFalse();
    expect($this->app['translator']->hasForLocale('30 Days'))->toBeTrue();
});

test('it can check key exists without triggering handle missing keys', function () {
    $this->app['translator']->handleMissingKeysUsing(function ($key) {
        $_SERVER['__missing_translation_key'] = $key;
    });

    expect($this->app['translator']->has('Foo Bar'))->toBeFalse();
    expect(isset($_SERVER['__missing_translation_key']))->toBeFalse();

    expect($this->app['translator']->hasForLocale('Foo Bar', 'nl'))->toBeFalse();
    expect(isset($_SERVER['__missing_translation_key']))->toBeFalse();
});

test('it can handle missing keys using callback', function () {
    $this->app['translator']->handleMissingKeysUsing(function ($key) {
        $_SERVER['__missing_translation_key'] = $key;

        return 'callback key';
    });

    $key = $this->app['translator']->get('some missing key');

    expect($key)->toBe('callback key');
    expect($_SERVER['__missing_translation_key'])->toBe('some missing key');

    $this->app['translator']->handleMissingKeysUsing(null);
});

test('it can handle missing keys no return', function () {
    $this->app['translator']->handleMissingKeysUsing(function ($key) {
        $_SERVER['__missing_translation_key'] = $key;
    });

    $key = $this->app['translator']->get('some missing key');

    expect($key)->toBe('some missing key');
    expect($_SERVER['__missing_translation_key'])->toBe('some missing key');

    $this->app['translator']->handleMissingKeysUsing(null);
});

test('it returns correct locale for missing keys', function () {
    $this->app['translator']->handleMissingKeysUsing(function ($key, $replacements, $locale) {
        $_SERVER['__missing_translation_key_locale'] = $locale;
    });

    $this->app['translator']->get('some missing key', [], 'ht');

    expect($_SERVER['__missing_translation_key_locale'])->toBe('ht');

    $this->app['translator']->handleMissingKeysUsing(null);
});

test('file validation does not attempt to translate already translated messages', function () {
    $keysLookedUp = [];

    $this->app['translator']->handleMissingKeysUsing(function ($key) use (&$keysLookedUp) {
        $keysLookedUp[] = $key;
    });

    $validator = $this->app['validator']->make(
        ['file' => UploadedFile::fake()->create('file.pdf')],
        ['file' => [File::types(['txt'])]]
    );

    $validator->fails();

    expect($keysLookedUp)->not->toContain('The file field must be a file of type: txt.');

    $this->app['translator']->handleMissingKeysUsing(null);
});

test('it can handle choice', function (int $count, string $expected, ?string $locale = null) {
    if (! is_null($locale)) {
        $this->app["translator"]->setLocale($locale);
    }

    $name = 'Taylor';

    expect($this->app['translator']->choice('tests::app.greeting', $count, ['name' => $name]))->toBe(strtr($expected, [':name' => $name, ':count' => $count]));
})->with('greetingChoiceDataProvider');

test('it can handle choice with choice separator in replace string', function (int $count, string $expected, ?string $locale = null) {
    if (! is_null($locale)) {
        $this->app["translator"]->setLocale($locale);
    }

    $name = 'Taylor | Laravel';

    expect($this->app['translator']->choice('tests::app.greeting', $count, ['name' => $name]))->toBe(strtr($expected, [':name' => $name, ':count' => $count]));
})->with('greetingChoiceDataProvider');

dataset('greetingChoiceDataProvider', function () {
    yield [0, 'Hello :name'];
    yield [3, 'Hello :name, you have 3 unread messages'];
    yield [0, 'Bonjour :name', 'fr'];
    yield [3, 'Bonjour :name, vous avez :count messages non lus', 'fr'];
});
