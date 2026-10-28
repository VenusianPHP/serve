<?php

namespace Venusian\Serve\Tests\View;

use ErrorException;
use Exception;
use Voyager\Contracts\Filesystem\FileNotFoundException;
use Voyager\Filesystem\Filesystem;
use Mariner\View\Compilers\CompilerInterface;
use Mariner\View\Engines\CompilerEngine;
use Mariner\View\ViewException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('views may be recompiled and rendered', function () {
    $engine = getEngine();
    $engine->getCompiler()->expects('getCompiledPath')->with(__DIR__.'/Fixtures/foo.php')->andReturn(__DIR__.'/Fixtures/basic.php');
    $engine->getCompiler()->expects('isExpired')->with(__DIR__.'/Fixtures/foo.php')->andReturn(true);
    $engine->getCompiler()->expects('compile')->with(__DIR__.'/Fixtures/foo.php');
    $results = $engine->get(__DIR__.'/Fixtures/foo.php');

    expect($results)->toBe('Hello World
');
});

test('views are not recompiled if they are not expired', function () {
    $engine = getEngine();
    $engine->getCompiler()->expects('getCompiledPath')->with(__DIR__.'/Fixtures/foo.php')->andReturn(__DIR__.'/Fixtures/basic.php');
    $engine->getCompiler()->expects('isExpired')->andReturn(false);
    $engine->getCompiler()->shouldReceive('compile')->never();
    $results = $engine->get(__DIR__.'/Fixtures/foo.php');

    expect($results)->toBe('Hello World
');
});

test('regular exceptions are re thrown as view exceptions', function () {
    $engine = getEngine();
    $engine->getCompiler()->expects('getCompiledPath')->with(__DIR__.'/Fixtures/foo.php')->andReturn(__DIR__.'/Fixtures/regular-exception.php');
    $engine->getCompiler()->expects('isExpired')->andReturn(false);

    $engine->get(__DIR__.'/Fixtures/foo.php');
})->throws(ViewException::class, 'regular exception message');

test('http exceptions are not re thrown as view exceptions', function () {
    $engine = getEngine();
    $engine->getCompiler()->expects('getCompiledPath')->with(__DIR__.'/Fixtures/foo.php')->andReturn(__DIR__.'/Fixtures/http-exception.php');
    $engine->getCompiler()->expects('isExpired')->andReturn(false);

    $engine->get(__DIR__.'/Fixtures/foo.php');
})->throws(HttpException::class, 'http exception message');

test('that views are not ask twice if they are expired', function () {
    $engine = getEngine();
    $engine->getCompiler()->expects('getCompiledPath')->times(4)->with(__DIR__.'/Fixtures/foo.php')->andReturn(__DIR__.'/Fixtures/basic.php');
    $engine->getCompiler()->expects('isExpired')->times(2)->andReturn(false);
    $engine->getCompiler()->shouldReceive('compile')->never();

    $engine->get(__DIR__.'/Fixtures/foo.php');
    $engine->get(__DIR__.'/Fixtures/foo.php');
    $engine->get(__DIR__.'/Fixtures/foo.php');

    $engine->forgetCompiledOrNotExpired();

    $engine->get(__DIR__.'/Fixtures/foo.php');
});

test('views are recompiled when compiled view is missing via file not found exception', function () {
    $compiled = __DIR__.'/Fixtures/basic.php';
    $path = __DIR__.'/Fixtures/foo.php';

    $files = Mockery::mock(Filesystem::class);
    $engine = getEngine($files);

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andReturn('compiled-content');

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new FileNotFoundException(
            "File does not exist at path {$path}."
        ));

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andReturn('compiled-content');

    $engine->getCompiler()
        ->expects('getCompiledPath')
        ->times(3)
        ->with($path)
        ->andReturn($compiled);

    $engine->getCompiler()
        ->expects('isExpired')
        ->andReturn(true);

    $engine->getCompiler()
        ->expects('compile')
        ->times(2)
        ->with($path);

    $engine->get($path);
    $engine->get($path);
});

test('views are recompiled when compiled view is missing via require exception', function () {
    $compiled = __DIR__.'/Fixtures/basic.php';
    $path = __DIR__.'/Fixtures/foo.php';

    $files = Mockery::mock(Filesystem::class);
    $engine = getEngine($files);

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andReturn('compiled-content');

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new ErrorException(
            "require({$path}): Failed to open stream: No such file or directory",
        ));

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andReturn('compiled-content');

    $engine->getCompiler()
        ->expects('getCompiledPath')
        ->times(3)
        ->with($path)
        ->andReturn($compiled);

    $engine->getCompiler()
        ->expects('isExpired')
        ->andReturn(true);

    $engine->getCompiler()
        ->expects('compile')
        ->times(2)
        ->with($path);

    $engine->get($path);
    $engine->get($path);
});

test('views are recompiled just once when compiled view is missing', function () {
    $compiled = __DIR__.'/Fixtures/basic.php';
    $path = __DIR__.'/Fixtures/foo.php';

    $files = Mockery::mock(Filesystem::class);
    $engine = getEngine($files);

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andReturn('compiled-content');

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new FileNotFoundException(
            "File does not exist at path {$path}."
        ));

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new FileNotFoundException(
            "File does not exist at path {$path}."
        ));

    $engine->getCompiler()
        ->expects('getCompiledPath')
        ->times(3)
        ->with($path)
        ->andReturn($compiled);

    $engine->getCompiler()
        ->expects('isExpired')
        ->andReturn(true);

    $engine->getCompiler()
        ->expects('compile')
        ->times(2)
        ->with($path);

    $engine->get($path);
    $engine->get($path);
})->throws(ViewException::class, "File does not exist at path ".__DIR__."/Fixtures/foo.php.");

test('views are not recompiled on regular view exception', function () {
    $compiled = __DIR__.'/Fixtures/basic.php';
    $path = __DIR__.'/Fixtures/foo.php';

    $files = Mockery::mock(Filesystem::class);
    $engine = getEngine($files);

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new Exception(
            'Just an regular error...'
        ));

    $engine->getCompiler()
        ->expects('isExpired')
        ->andReturn(false);

    $engine->getCompiler()
        ->shouldReceive('compile')
        ->never();

    $engine->getCompiler()
        ->expects('getCompiledPath')
        ->with($path)
        ->andReturn($compiled);
    $engine->get($path);
})->throws(ViewException::class, 'Just an regular error...');

test('views are not recompiled if they were just compiled', function () {
    $compiled = __DIR__.'/Fixtures/basic.php';
    $path = __DIR__.'/Fixtures/foo.php';

    $files = Mockery::mock(Filesystem::class);
    $engine = getEngine($files);

    $files->expects('getRequire')
        ->with($compiled, [])
        ->andThrow(new FileNotFoundException(
            "File does not exist at path {$path}."
        ));

    $engine->getCompiler()
        ->expects('isExpired')
        ->andReturn(true);

    $engine->getCompiler()
        ->expects('compile')
        ->with($path);

    $engine->getCompiler()
        ->expects('getCompiledPath')
        ->with($path)
        ->andReturn($compiled);
    $engine->get($path);
})->throws(ViewException::class, "File does not exist at path ".__DIR__."/Fixtures/foo.php.");

function getEngine($filesystem = null)
{
    return new CompilerEngine(Mockery::mock(CompilerInterface::class), $filesystem ?: new Filesystem);
}
