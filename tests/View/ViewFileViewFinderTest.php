<?php

namespace Venusian\Serve\Tests\View;

use Voyager\Filesystem\Filesystem;
use Mariner\View\FileViewFinder;
use InvalidArgumentException;
use Mockery;

test('basic view finding', function () {
    $finder = getFinder();
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.blade.php')->andReturn(true);

    expect($finder->find('foo'))->toEqual(__DIR__.'/foo.blade.php');
});

test('cascading file loading', function () {
    $finder = getFinder();
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.blade.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.php')->andReturn(true);

    expect($finder->find('foo'))->toEqual(__DIR__.'/foo.php');
});

test('directory cascading file loading', function () {
    $finder = getFinder();
    $finder->addLocation(__DIR__.'/nested');
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.blade.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.css')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.html')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/nested/foo.blade.php')->andReturn(true);

    expect($finder->find('foo'))->toEqual(__DIR__.'/nested/foo.blade.php');
});

test('namespaced basic file loading', function () {
    $finder = getFinder();
    $finder->addNamespace('foo', __DIR__.'/foo');
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.blade.php')->andReturn(true);

    expect($finder->find('foo::bar.baz'))->toEqual(__DIR__.'/foo/bar/baz.blade.php');
});

test('cascading namespaced file loading', function () {
    $finder = getFinder();
    $finder->addNamespace('foo', __DIR__.'/foo');
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.blade.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.php')->andReturn(true);

    expect($finder->find('foo::bar.baz'))->toEqual(__DIR__.'/foo/bar/baz.php');
});

test('directory cascading namespaced file loading', function () {
    $finder = getFinder();
    $finder->addNamespace('foo', [__DIR__.'/foo', __DIR__.'/bar']);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.blade.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.css')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo/bar/baz.html')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/bar/bar/baz.blade.php')->andReturn(true);

    expect($finder->find('foo::bar.baz'))->toEqual(__DIR__.'/bar/bar/baz.blade.php');
});

test('exception thrown when view not found', function () {

    $finder = getFinder();
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.blade.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.php')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.css')->andReturn(false);
    $finder->getFilesystem()->expects('exists')->with(__DIR__.'/foo.html')->andReturn(false);

    $finder->find('foo');
})->throws(InvalidArgumentException::class);

test('exception thrown on invalid view name', function () {

    $finder = getFinder();
    $finder->find('name::');
})->throws(InvalidArgumentException::class, 'No hint path defined for [name].');

test('exception thrown when no hint path is registered', function () {

    $finder = getFinder();
    $finder->find('name::foo');
})->throws(InvalidArgumentException::class, 'No hint path defined for [name].');

test('adding extension prepends not appends', function () {
    $finder = getFinder();
    $finder->addExtension('baz');
    $extensions = $finder->getExtensions();
    expect(reset($extensions))->toBe('baz');
});

test('adding extensions replaces old ones', function () {
    $finder = getFinder();
    $finder->addExtension('baz');
    $finder->addExtension('baz');

    expect($finder->getExtensions())->toHaveCount(5);
});

test('passing view with hint returns true', function () {
    $finder = getFinder();

    expect($finder->hasHintInformation('hint::foo.bar'))->toBeTrue();
});

test('passing view without hint returns false', function () {
    $finder = getFinder();

    expect($finder->hasHintInformation('foo.bar'))->toBeFalse();
});

test('passing view with false hint returns false', function () {
    $finder = getFinder();

    expect($finder->hasHintInformation('::foo.bar'))->toBeFalse();
});

dataset('pathsProvider', function () {
    return [
        ['incorrect_path', 'incorrect_path'],
    ];
});

test('normalized paths', function ($originalPath, $exceptedPath) {
    $finder = getFinder();
    $finder->prependLocation($originalPath);
    $normalizedPath = $finder->getPaths()[0];
    expect($normalizedPath)->toBe($exceptedPath);
})->with('pathsProvider');

function getFinder()
{
    return new FileViewFinder(Mockery::mock(Filesystem::class), [__DIR__]);
}
