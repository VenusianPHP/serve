<?php

namespace Venusian\Serve\Tests\Image;

use Voyager\Config\Repository;
use Voyager\Contracts\Filesystem\Factory as FilesystemFactory;
use Mariner\Contracts\Filesystem\Filesystem as FilesystemContract;
use Voyager\Contracts\Core\FrameworkCore;
use Mariner\Contracts\Image\Driver;
use Mariner\Contracts\Image\Transformation;
use Voyager\Filesystem\Filesystem;
use Voyager\Http\Client\RequestException;
use Voyager\Filesystem\UploadedFile;
use Mariner\Image\Image;
use Mariner\Image\ImageException;
use Mariner\Image\ImageManager;
use Mariner\Image\ImagePipeline;
use InvalidArgumentException;
use Mockery;
use Voyager\Http\Client\Factory;

test('default driver returns configured value', function () {
    $app = makeApp(['images.default' => 'imagick']);

    $manager = new ImageManager($app);

    expect($manager->getDefaultDriver())->toBe('imagick');
});

test('default driver falls back to gd', function () {
    $app = makeApp([]);

    $manager = new ImageManager($app);

    expect($manager->getDefaultDriver())->toBe('gd');
});

test('extend registers custom driver', function () {
    $app = makeApp(['images.default' => 'custom']);

    $mockDriver = new ImageManagerTestDriverStub;

    $manager = new ImageManager($app);
    $manager->extend('custom', function ($app) use ($mockDriver) {
        return $mockDriver;
    });

    expect($manager->driver('custom'))->toBe($mockDriver);
});

test('driver caches resolved instances', function () {
    $app = makeApp([]);

    $mockDriver = new ImageManagerTestDriverStub;

    $manager = new ImageManager($app);
    $manager->extend('custom', function () use ($mockDriver) {
        return $mockDriver;
    });

    $first = $manager->driver('custom');
    $second = $manager->driver('custom');

    expect($second)->toBe($first);
});

test('throws for unsupported driver', function () {
    $app = makeApp([]);

    $manager = new ImageManager($app);

    $manager->driver('nonexistent');
})->throws(InvalidArgumentException::class, 'Image driver [nonexistent] is not supported.');

test('from bytes returns image with contents', function () {
    $app = makeApp([]);
    $manager = new ImageManager($app);

    $contents = managerFakeImageContents();
    $image = $manager->fromBytes($contents);

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);
});

test('from path returns image from file path', function () {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);
    $path = $file->getRealPath();

    $app = makeApp([]);
    $app->expects('make')
        ->with(Filesystem::class)
        ->andReturn(new Filesystem);

    $manager = new ImageManager($app);
    $image = $manager->fromPath($path);

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->not->toBeEmpty();
});

test('from path is lazy', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('get');

    $app = makeApp([]);

    $manager = new ImageManager($app);
    $image = $manager->fromPath('/some/path.jpg');

    expect($image)->toBeInstanceOf(Image::class);
});

test('from storage returns image from storage disk path', function () {
    $contents = managerFakeImageContents();

    $disk = Mockery::mock(FilesystemContract::class);
    $disk->expects('get')
        ->with('images/avatar.jpg')
        ->andReturn($contents);

    $filesystem = Mockery::mock(FilesystemFactory::class);
    $filesystem->expects('disk')
        ->with('public')
        ->andReturn($disk);

    $app = makeApp([]);
    $app->expects('make')
        ->with(FilesystemFactory::class)
        ->andReturn($filesystem);

    $manager = new ImageManager($app);
    $image = $manager->fromStorage('images/avatar.jpg', 'public');

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);
});

test('from storage accepts backed enum disk', function () {
    $contents = managerFakeImageContents();

    $disk = Mockery::mock(FilesystemContract::class);
    $disk->expects('get')
        ->with('images/avatar.jpg')
        ->andReturn($contents);

    $filesystem = Mockery::mock(FilesystemFactory::class);
    $filesystem->expects('disk')
        ->with('public')
        ->andReturn($disk);

    $app = makeApp([]);
    $app->expects('make')
        ->with(FilesystemFactory::class)
        ->andReturn($filesystem);

    $manager = new ImageManager($app);
    $image = $manager->fromStorage('images/avatar.jpg', ImageDiskStub::Public);

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);
});

test('from storage is lazy', function () {
    $filesystem = Mockery::mock(FilesystemFactory::class);
    $filesystem->shouldNotReceive('disk');

    $app = makeApp([]);

    $manager = new ImageManager($app);
    $image = $manager->fromStorage('images/avatar.jpg', 'public');

    expect($image)->toBeInstanceOf(Image::class);
});

test('from stream returns image', function () {
    $contents = managerFakeImageContents();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $app = makeApp([]);
    $manager = new ImageManager($app);
    $image = $manager->fromStream($stream);

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);

    fclose($stream);
});

test('from stream is lazy', function () {
    $contents = managerFakeImageContents();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $app = makeApp([]);
    $manager = new ImageManager($app);
    $image = $manager->fromStream($stream);

    expect($image)->toBeInstanceOf(Image::class);
    expect(ftell($stream))->toBe(0);

    fclose($stream);
});

test('from stream contents are only read once', function () {
    $contents = managerFakeImageContents();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $app = makeApp([]);
    $manager = new ImageManager($app);
    $image = $manager->fromStream($stream);

    expect($image->toBytes())->toBe($contents);
    expect($image->toBytes())->toBe($contents);
    expect($image->dimensions())->toBe([100, 100]);

    fclose($stream);
});

test('from stream contents are shared between clones', function () {
    $contents = managerFakeImageContents();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $app = makeApp([]);
    $manager = new ImageManager($app);
    $image = $manager->fromStream($stream);

    $first = $image->usingGd();
    $second = $image->usingImagick();

    expect($first->toBytes())->toBe($contents);
    expect($second->toBytes())->toBe($contents);

    fclose($stream);
});

test('lazy contents are not shared between different images', function () {
    $app = makeApp([]);
    $manager = new ImageManager($app);

    $first = $manager->fromBase64(base64_encode('first'));
    $second = $manager->fromBase64(base64_encode('second'));

    expect($first->toBytes())->toBe('first');
    expect($second->toBytes())->toBe('second');
    expect($first->toBytes())->toBe('first');
});

test('from url is only fetched once', function () {
    $contents = managerFakeImageContents();

    $http = new Factory;
    $http->fake([
        'https://example.com/photo.jpg' => Factory::response($contents, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $app = makeApp([]);
    $app->shouldReceive('make')
        ->with(Factory::class)
        ->andReturn($http);

    $manager = new ImageManager($app);
    $image = $manager->fromUrl('https://example.com/photo.jpg');

    expect($image->toBytes())->toBe($contents);
    expect($image->toBytes())->toBe($contents);
    expect($image->dimensions())->toBe([100, 100]);

    $http->assertSentCount(1);
});

test('from stream throws for invalid data', function () {
    $stream = fopen('php://memory', 'r+');

    $app = makeApp([]);
    $manager = new ImageManager($app);

    $manager->fromStream($stream)->toBytes();

    fclose($stream);
})->throws(ImageException::class, 'Invalid stream image data.');

test('from upload returns image from uploaded file', function () {
    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

    $app = makeApp([]);
    $manager = new ImageManager($app);
    $image = $manager->fromUpload($file);

    expect($image)->toBeInstanceOf(Image::class);
    expect(file_get_contents($file->getRealPath()))->toBe($image->toBytes());
    expect($image->file())->toBe($file);
});

test('from url returns image', function () {
    $contents = managerFakeImageContents();

    $http = new Factory;
    $http->fake([
        'https://example.com/photo.jpg' => Factory::response(
            $contents,
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $app = makeApp([]);
    $app->expects('make')
        ->with(Factory::class)
        ->andReturn($http);

    $manager = new ImageManager($app);
    $image = $manager->fromUrl('https://example.com/photo.jpg');

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);
});

test('from url throws for unsuccessful response', function () {
    $http = new Factory;
    $http->fake([
        'https://example.com/missing.jpg' => Factory::response('not found', 404),
    ]);

    $app = makeApp([]);
    $app->expects('make')
        ->with(Factory::class)
        ->andReturn($http);

    $manager = new ImageManager($app);
    $image = $manager->fromUrl('https://example.com/missing.jpg');

    expect($image)->toBeInstanceOf(Image::class);

    $image->toBytes();
})->throws(RequestException::class);

test('from url is lazy', function () {
    $http = new Factory;
    $http->fake();

    $app = makeApp([]);
    $app->allows('make')
        ->with(Factory::class)
        ->andReturn($http);

    $manager = new ImageManager($app);
    $image = $manager->fromUrl('https://example.com/photo.jpg');

    expect($image)->toBeInstanceOf(Image::class);

    $http->assertNothingSent();
});

test('from base64 returns image', function () {
    $contents = managerFakeImageContents();
    $base64 = base64_encode($contents);

    $app = makeApp([]);
    $manager = new ImageManager($app);

    $image = $manager->fromBase64($base64);

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->toBytes())->toBe($contents);
});

test('from base64 throws for invalid data', function () {
    $app = makeApp([]);
    $manager = new ImageManager($app);

    $manager->fromBase64('!!!not-base64!!!')->toBytes();
})->throws(ImageException::class, 'Invalid base64 image data.');

test('extend overwrites previous registration', function () {
    $app = makeApp([]);

    $firstDriver = new ImageManagerTestDriverStub;
    $secondDriver = new ImageManagerTestDriverStub;

    $manager = new ImageManager($app);
    $manager->extend('custom', fn () => $firstDriver);
    $manager->extend('custom', fn () => $secondDriver);

    expect($manager->driver('custom'))->toBe($secondDriver);
});

test('driver caches separately by name', function () {
    $app = makeApp([]);

    $driver1 = new ImageManagerTestDriverStub;
    $driver2 = new ImageManagerTestDriverStub;

    $manager = new ImageManager($app);
    $manager->extend('one', fn () => $driver1);
    $manager->extend('two', fn () => $driver2);

    expect($manager->driver('one'))->toBe($driver1);
    expect($manager->driver('two'))->toBe($driver2);
    expect($manager->driver('two'))->not->toBe($manager->driver('one'));
});

test('transform using applies handlers to new driver instances', function () {
    $app = makeApp([]);
    $driver = new class implements Driver
    {
        public array $handlers = [];

        public function process(string $contents, ImagePipeline $pipeline): string
        {
            return $contents;
        }

        public function dominantColor(string $contents): string
        {
            return '#000000';
        }

        public function dimensions(string $contents): array
        {
            return [0, 0];
        }

        public function transformUsing(string $transformation, callable $callback): static
        {
            $this->handlers[$transformation] = $callback;

            return $this;
        }
    };
    $transformation = new class implements Transformation {
        //
    };
    $callback = fn () => null;

    $manager = new ImageManager($app);
    $manager->extend('custom', fn () => $driver);
    $manager->transformUsing('custom', $transformation::class, $callback);

    expect($manager->driver('custom')->handlers[$transformation::class])->toBe($callback);
});

test('transform using applies handlers to resolved driver instances', function () {
    $app = makeApp([]);
    $driver = new class implements Driver
    {
        public array $handlers = [];

        public function process(string $contents, ImagePipeline $pipeline): string
        {
            return $contents;
        }

        public function dominantColor(string $contents): string
        {
            return '#000000';
        }

        public function dimensions(string $contents): array
        {
            return [0, 0];
        }

        public function transformUsing(string $transformation, callable $callback): static
        {
            $this->handlers[$transformation] = $callback;

            return $this;
        }
    };
    $transformation = new class implements Transformation {
        //
    };
    $callback = fn () => null;

    $manager = new ImageManager($app);
    $manager->extend('custom', fn () => $driver);
    $manager->driver('custom');
    $manager->transformUsing('custom', $transformation::class, $callback);

    expect($driver->handlers[$transformation::class])->toBe($callback);
});

function managerFakeImageContents(): string
{
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    return file_get_contents($file->getRealPath());
}

function makeApp(array $config): FrameworkCore
{
    $app = Mockery::mock(FrameworkCore::class, \ArrayAccess::class);

    $configRepo = new Repository($config);

    $app->shouldReceive('make')->with('config')->andReturn($configRepo)->byDefault();
    $app->shouldReceive('offsetGet')->with('config')->andReturn($configRepo);
    $app->shouldReceive('offsetExists')->andReturn(true);

    return $app;
}

enum ImageDiskStub: string
{
    case Public = 'public';
}

class ImageManagerTestDriverStub implements Driver
{
    public function process(string $contents, ImagePipeline $pipeline): string
    {
        return $contents;
    }

    public function dominantColor(string $contents): string
    {
        return '#000000';
    }

    public function dimensions(string $contents): array
    {
        return [0, 0];
    }

    public function transformUsing(string $transformation, callable $callback): static
    {
        return $this;
    }
}
