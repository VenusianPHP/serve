<?php

namespace Venusian\Serve\Tests\View;

use Voyager\Filesystem\Filesystem;
use Mariner\View\Compilers\BladeCompiler;
use InvalidArgumentException;
use Mockery;

test('is expired returns true if compiled file doesnt exist', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    expect($compiler->isExpired('foo'))->toBeTrue();
});

test('cannot construct with bad cache path', function () {

    new BladeCompiler(getFiles(), null);
})->throws(InvalidArgumentException::class, 'Please provide a valid cache path.');

test('is expired returns true when modification times warrant', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(true);
    $files->expects('lastModified')->with('foo')->andReturn(100);
    $files->expects('lastModified')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(0);
    expect($compiler->isExpired('foo'))->toBeTrue();
});

test('is expired returns false when use cache is true and no file modification', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(true);
    $files->expects('lastModified')->with('foo')->andReturn(0);
    $files->expects('lastModified')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(100);
    expect($compiler->isExpired('foo'))->toBeFalse();
});

test('is expired returns true when use cache is false', function () {
    $compiler = new BladeCompiler(getFiles(), __DIR__, shouldCache: false);
    expect($compiler->isExpired('foo'))->toBeTrue();
});

test('is expired returns false when ignore cache timestamps is true', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__, shouldCheckTimestamps: false);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(true);
    expect($compiler->isExpired('foo'))->toBeFalse();
});

test('compile path is properly created', function () {
    $compiler = new BladeCompiler(getFiles(), __DIR__);
    expect($compiler->getCompiledPath('foo'))->toEqual(__DIR__.'/'.hash('xxh128', 'v2foo').'.php');
});

test('compile compiles file and returns contents', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php', 'Hello World<?php /**PATH foo ENDPATH**/ ?>');
    $compiler->compile('foo');
});

test('compile compiles file and returns contents creating directory', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php', 'Hello World<?php /**PATH foo ENDPATH**/ ?>');
    $compiler->compile('foo');
});

test('compile updates cache if changed', function () {
    $compiledPath = __DIR__.'/'.hash('xxh128', 'v2foo').'.php';
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with($compiledPath)->andReturn(true);
    $files->expects('hash')->with($compiledPath, 'xxh128')->andReturn(hash('xxh128', 'outdated content'));
    $files->expects('replace')->with($compiledPath, 'Hello World<?php /**PATH foo ENDPATH**/ ?>');
    $compiler->compile('foo');
});

test('compile keeps cache if unchanged', function () {
    $compiledPath = __DIR__.'/'.hash('xxh128', 'v2foo').'.php';
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(false);
    $files->expects('makeDirectory')->with(__DIR__, 0777, true, true);
    $files->expects('exists')->with($compiledPath)->andReturn(true);
    $files->expects('hash')->with($compiledPath, 'xxh128')->andReturn(hash('xxh128', 'Hello World<?php /**PATH foo ENDPATH**/ ?>'));
    $files->expects('lastModified')->with('foo')->andReturn(100);
    $files->expects('lastModified')->with($compiledPath)->andReturn(200);
    $compiler->compile('foo');
});

test('compile refreshes cache timestamp if unchanged but expired', function () {
    $files = new Filesystem;
    $directory = sys_get_temp_dir().'/laravel-blade-compiler-test-'.uniqid();
    $source = $directory.'/source.blade.php';
    $cache = $directory.'/cache';

    try {
        $files->ensureDirectoryExists($cache);
        $files->put($source, 'Hello World');

        $compiler = new BladeCompiler($files, $cache);
        $compiler->compile($source);

        $compiled = $compiler->getCompiledPath($source);

        $compiledModified = time();
        $sourceModified = $compiledModified + 10;

        touch($source, $sourceModified);
        touch($compiled, $compiledModified);

        clearstatcache(true, $source);
        clearstatcache(true, $compiled);

        expect($compiler->isExpired($source))->toBeTrue();

        $compiler->compile($source);

        clearstatcache(true, $source);
        clearstatcache(true, $compiled);

        expect($files->lastModified($compiled))->toBeGreaterThan($files->lastModified($source));
        expect($compiler->isExpired($source))->toBeFalse();
    } finally {
        $files->deleteDirectory($directory);
    }
});

test('compile compiles and get the path', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php', 'Hello World<?php /**PATH foo ENDPATH**/ ?>');
    $compiler->compile('foo');
    expect($compiler->getPath())->toBe('foo');
});

test('compile set and get the path', function () {
    $compiler = new BladeCompiler(getFiles(), __DIR__);
    $compiler->setPath('foo');
    expect($compiler->getPath())->toBe('foo');
});

test('compile with path set before', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php', 'Hello World<?php /**PATH foo ENDPATH**/ ?>');
    // set path before compilation
    $compiler->setPath('foo');
    // trigger compilation with $path
    $compiler->compile();
    expect($compiler->getPath())->toBe('foo');
});

test('raw tags can be set to legacy values', function () {
    $compiler = new BladeCompiler(getFiles(), __DIR__);
    $compiler->setEchoFormat('%s');

    expect($compiler->compileString('{{{ $name }}}'))->toBe('<?php echo e($name); ?>');
    expect($compiler->compileString('{{ $name }}'))->toBe('<?php echo $name; ?>');
    expect($compiler->compileString('{{
            $name
        }}'))->toBe('<?php echo $name; ?>');
});

test('include path to template', function ($content, $compiled) {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('foo')->andReturn($content);
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2foo').'.php', $compiled);

    $compiler->compile('foo');
})->with('appendViewPathDataProvider');

dataset('appendViewPathDataProvider', function () {
    return [
        'No PHP blocks' => [
            'Hello World',
            'Hello World<?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Single PHP block without closing ?>' => [
            '<?php echo $path',
            '<?php echo $path ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Ending PHP block.' => [
            'Hello world<?php echo $path ?>',
            'Hello world<?php echo $path ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Ending PHP block without closing ?>' => [
            'Hello world<?php echo $path',
            'Hello world<?php echo $path ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
        'PHP block between content.' => [
            'Hello world<?php echo $path ?>Hi There',
            'Hello world<?php echo $path ?>Hi There<?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Multiple PHP blocks.' => [
            'Hello world<?php echo $path ?>Hi There<?php echo $path ?>Hello Again',
            'Hello world<?php echo $path ?>Hi There<?php echo $path ?>Hello Again<?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Multiple PHP blocks without closing ?>' => [
            'Hello world<?php echo $path ?>Hi There<?php echo $path',
            'Hello world<?php echo $path ?>Hi There<?php echo $path ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Short open echo tag' => [
            'Hello world<?= echo $path',
            'Hello world<?= echo $path ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
        'Echo XML declaration' => [
            '<?php echo \'<?xml version="1.0" encoding="UTF-8"?>\';',
            '<?php echo \'<?xml version="1.0" encoding="UTF-8"?>\'; ?><?php /**PATH foo ENDPATH**/ ?>',
        ],
    ];
});

test('dont include empty path', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2').'.php', 'Hello World');
    $compiler->setPath('');
    $compiler->compile();
});

// Voyager's Filesystem::get() takes a string, so "no path" is the empty string here rather than null.
test('dont include null path', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);
    $files->expects('get')->with('')->andReturn('Hello World');
    $files->expects('exists')->with(__DIR__)->andReturn(true);
    $files->expects('exists')->with(__DIR__.'/'.hash('xxh128', 'v2').'.php')->andReturn(false);
    $files->expects('replace')->with(__DIR__.'/'.hash('xxh128', 'v2').'.php', 'Hello World');
    $compiler->setPath('');
    $compiler->compile();
});

test('should start from strict types declaration', function () {
    $compiler = new BladeCompiler(getFiles(), __DIR__);
    $strictTypeDecl = "<?php\ndeclare(strict_types = 1);";
    expect($strictTypeDecl)->toBe(substr($compiler->compileString("<?php\ndeclare(strict_types = 1);\nHello World"),
        0, strlen($strictTypeDecl)));
});

test('component aliases can be conventionally determined', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->component('App\Foo\Bar');
    expect($compiler->getClassComponentAliases())->toEqual(['bar' => 'App\Foo\Bar']);

    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->component('App\Foo\Bar', null, 'prefix');
    expect($compiler->getClassComponentAliases())->toEqual(['prefix-bar' => 'App\Foo\Bar']);

    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->component('App\View\Components\Forms\Input');
    expect($compiler->getClassComponentAliases())->toEqual(['forms:input' => 'App\View\Components\Forms\Input']);

    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->component('App\View\Components\Forms\Input', null, 'prefix');
    expect($compiler->getClassComponentAliases())->toEqual(['prefix-forms:input' => 'App\View\Components\Forms\Input']);
});

test('anonymous component namespaces can be stored', function () {
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->anonymousComponentNamespace(' public/frontend ', 'frontend');
    expect($compiler->getAnonymousComponentNamespaces())->toEqual(['frontend' => 'public.frontend']);

    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->anonymousComponentNamespace('public/frontend/', 'frontend');
    expect($compiler->getAnonymousComponentNamespaces())->toEqual(['frontend' => 'public.frontend']);

    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->anonymousComponentNamespace('/admin/components', 'admin');
    expect($compiler->getAnonymousComponentNamespaces())->toEqual(['admin' => 'admin.components']);

    // Test directory is automatically inferred from the prefix if not given.
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->anonymousComponentNamespace('frontend');
    expect($compiler->getAnonymousComponentNamespaces())->toEqual(['frontend' => 'frontend']);

    // Test that the prefix can also contain dots.
    $compiler = new BladeCompiler($files = getFiles(), __DIR__);

    $compiler->anonymousComponentNamespace('frontend/auth', 'frontend.auth');
    expect($compiler->getAnonymousComponentNamespaces())->toEqual(['frontend.auth' => 'frontend.auth']);
});

function getFiles()
{
    return Mockery::mock(Filesystem::class);
}
