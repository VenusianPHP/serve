<?php

namespace Venusian\Serve\Tests\Translation;

use Voyager\Filesystem\Filesystem;
use Mariner\Translation\FileLoader;
use Mockery;

test('load method loads translations from added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');

    $files->expects('exists')->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->andReturn(['baz' => 'backagesplash']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash']);
});

test('load method ignores locales and groups containing path traversal', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldNotReceive('exists');
    $files->shouldNotReceive('getRequire');
    $loader = new FileLoader($files, __DIR__);
    $loader->addNamespace('namespace', __DIR__.'/namespace');

    expect($loader->load('../secret', 'config'))->toEqual([]);
    expect($loader->load('..\\secret', 'config'))->toEqual([]);
    expect($loader->load('..', 'secret/config'))->toEqual([]);
    expect($loader->load("en\0", 'messages'))->toEqual([]);
    expect($loader->load('en', '../../secret/config'))->toEqual([]);
    expect($loader->load('en', '..\\secret\\config'))->toEqual([]);
    expect($loader->load('../secret', 'config', 'namespace'))->toEqual([]);
    expect($loader->load('../secret', '*', '*'))->toEqual([]);
});

test('load method allows groups in subdirectories', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);

    $files->expects('exists')->with(__DIR__.'/en/admin/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/admin/messages.php')->andReturn(['foo' => 'bar']);

    expect($loader->load('en', 'admin/messages'))->toEqual(['foo' => 'bar']);
});

test('load method handles missing added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/missing');

    $files->expects('exists')->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->expects('exists')->with(__DIR__.'/missing/en/messages.php')->andReturn(false);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar']);
});

test('load method overwrites existing keys from added path', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');

    $files->expects('exists')->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->andReturn(['foo' => 'baz']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'baz']);
});

test('load method loads translations from multiple added paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addPath(__DIR__.'/another');
    $loader->addPath(__DIR__.'/yet-another');

    $files->expects('exists')->with(__DIR__.'/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/messages.php')->andReturn(['foo' => 'bar']);

    $files->expects('exists')->with(__DIR__.'/another/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/another/en/messages.php')->andReturn(['baz' => 'backagesplash']);

    $files->expects('exists')->with(__DIR__.'/yet-another/en/messages.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/yet-another/en/messages.php')->andReturn(['qux' => 'quux']);

    expect($loader->load('en', 'messages'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash', 'qux' => 'quux']);
});

test('load method without namespaces properly calls loader', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $files->expects('exists')->with(__DIR__.'/en/foo.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/foo.php')->andReturn(['messages']);

    expect($loader->load('en', 'foo', null))->toEqual(['messages']);
});

test('load method without namespaces properly calls loader with multiple paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with(__DIR__.'/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/second/en/foo.php')->andReturn(true);
    $files->expects('getRequire')->with(__DIR__.'/en/foo.php')->andReturn(['messages' => 'first']);
    $files->expects('getRequire')->with(__DIR__.'/second/en/foo.php')->andReturn(['messages' => 'second']);
    $loader = new FileLoader($files, [__DIR__, __DIR__.'/second']);

    expect($loader->load('en', 'foo', null))->toEqual(['messages' => 'second']);
});

test('load method with namespaces properly calls loader', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with('bar/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(false);
    $files->expects('getRequire')->with('bar/en/foo.php')->andReturn(['foo' => 'bar']);
    $loader = new FileLoader($files, __DIR__);
    $loader->addNamespace('namespace', 'bar');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'bar']);
});

test('load method with namespaces properly calls loader with multiple paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(false);
    $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(false);
    $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $loader = new FileLoader($files, [__DIR__, __DIR__.'/second']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'bar']);
});

test('load method with namespaces properly calls loader and loads local overrides', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with('bar/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->expects('getRequire')->with('bar/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $loader = new FileLoader($files, __DIR__);
    $loader->addNamespace('namespace', 'bar');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override', 'baz' => 'boom']);
});

test('load method with namespaces properly calls loader and loads local overrides with multiple paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(true);
    $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $files->expects('getRequire')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override-2', 'baz' => 'boom-2']);
    $loader = new FileLoader($files, [__DIR__, __DIR__.'/second']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override-2', 'baz' => 'boom-2']);
});

test('load method with namespaces properly calls loader and loads local overrides with multiple paths with missing key', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with('test-namespace-dir/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(true);
    $files->expects('getRequire')->with('test-namespace-dir/en/foo.php')->andReturn(['foo' => 'bar']);
    $files->expects('getRequire')->with(__DIR__.'/vendor/namespace/en/foo.php')->andReturn(['foo' => 'override', 'baz' => 'boom']);
    $files->expects('getRequire')->with(__DIR__.'/second/vendor/namespace/en/foo.php')->andReturn(['baz' => 'boom-2']);
    $loader = new FileLoader($files, [__DIR__, __DIR__.'/second']);
    $loader->addNamespace('namespace', 'test-namespace-dir');

    expect($loader->load('en', 'foo', 'namespace'))->toEqual(['foo' => 'override', 'baz' => 'boom-2']);
});

test('empty arrays returned when files dont exist', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with(__DIR__.'/en/foo.php')->andReturn(false);
    $files->shouldReceive('getRequire')->never();
    $loader = new FileLoader($files, __DIR__);

    expect($loader->load('en', 'foo', null))->toBe([]);
});

test('empty arrays returned when files dont exist for namespaced items', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('getRequire')->never();
    $loader = new FileLoader($files, __DIR__);

    expect($loader->load('en', 'foo', 'bar'))->toBe([]);
});

test('load method for jsonproperly calls loader', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->expects('exists')->with(__DIR__.'/en.json')->andReturn(true);
    $files->expects('get')->with(__DIR__.'/en.json')->andReturn('{"foo":"bar"}');
    $loader = new FileLoader($files, __DIR__);

    expect($loader->load('en', '*', '*'))->toEqual(['foo' => 'bar']);
});

test('load method for jsonproperly calls loader for multiple paths', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addJsonPath(__DIR__.'/another');

    $files->expects('exists')->with(__DIR__.'/en.json')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/another/en.json')->andReturn(true);
    $files->expects('get')->with(__DIR__.'/en.json')->andReturn('{"foo":"bar"}');
    $files->expects('get')->with(__DIR__.'/another/en.json')->andReturn('{"foo":"backagebar", "baz": "backagesplash"}');

    expect($loader->load('en', '*', '*'))->toEqual(['foo' => 'bar', 'baz' => 'backagesplash']);
});

test('load method for jsonpreserves numeric keys', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addJsonPath(__DIR__.'/another');

    $files->expects('exists')->with(__DIR__.'/en.json')->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/another/en.json')->andReturn(true);
    $files->expects('get')->with(__DIR__.'/en.json')->andReturn('{"404":"Page not found","foo":"bar"}');
    $files->expects('get')->with(__DIR__.'/another/en.json')->andReturn('{"404":"Not found","500":"Server error"}');

    expect($loader->load('en', '*', '*'))->toBe([404 => 'Page not found', 500 => 'Server error', 'foo' => 'bar']);
});

test('load method throw exception when provide invalid json', function () {
    $files = Mockery::mock(Filesystem::class);
    $loader = new FileLoader($files, __DIR__);
    $loader->addJsonPath(__DIR__.'/invalid');

    $invalidJsonString = '.{"foo":"cricket", "baz": "football"}';
    $files->expects('exists')->with(__DIR__.'/invalid/en.json')->andReturn(true);
    $files->expects('get')->with(__DIR__.'/invalid/en.json')->andReturn($invalidJsonString);
    $loader->load('en', '*', '*');
})->throws(\RuntimeException::class);

test('all registered namespace return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $loader->addNamespace('namespace', 'foo');
    $loader->addNamespace('namespace2', 'bar');
    expect($loader->namespaces())->toEqual(['namespace' => 'foo', 'namespace2' => 'bar']);
});

test('all added json paths return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $path1 = __DIR__.'/another';
    $path2 = __DIR__.'/another2';
    $loader->addJsonPath($path1);
    $loader->addJsonPath($path2);
    expect($loader->jsonPaths())->toEqual([$path1, $path2]);
});

test('all added paths return properly', function () {
    $loader = new FileLoader(Mockery::mock(Filesystem::class), __DIR__);
    $path1 = __DIR__.'/another';
    $path2 = __DIR__.'/another2';
    $loader->addPath($path1);
    $loader->addPath($path2);
    expect(array_slice($loader->paths(), 1))->toEqual([$path1, $path2]);
});
