<?php

namespace Venusian\Serve\Tests\Image;

use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Image\Driver;
use Voyager\Filesystem\UploadedFile;
use Mariner\Image\Image;
use Mariner\Image\ImageException;
use Mariner\Image\ImageManager;
use Mariner\Image\ImageOutputOptions;
use Mariner\Image\ImagePipeline;
use Mariner\Image\Transformations\Blur;
use Mariner\Image\Transformations\Contain;
use Mariner\Image\Transformations\Cover;
use Mariner\Image\Transformations\Crop;
use Mariner\Image\Transformations\FlipHorizontally;
use Mariner\Image\Transformations\FlipVertically;
use Mariner\Image\Transformations\Grayscale;
use Mariner\Image\Transformations\Orient;
use Mariner\Image\Transformations\Resize;
use Mariner\Image\Transformations\Rotate;
use Mariner\Image\Transformations\Scale;
use Mariner\Image\Transformations\Sharpen;

test('cover returns new instance', function () {
    $image = makeImage();
    $result = $image->cover(100, 200);

    expect($result)->not->toBe($image);
});

test('scale returns new instance', function () {
    $image = makeImage();
    $result = $image->scale(800, 600);

    expect($result)->not->toBe($image);
});

test('contain returns new instance', function () {
    $image = makeImage();
    $result = $image->contain(800, 600);

    expect($result)->not->toBe($image);
});

test('crop returns new instance', function () {
    $image = makeImage();
    $result = $image->crop(100, 100);

    expect($result)->not->toBe($image);
});

test('resize returns new instance', function () {
    $image = makeImage();
    $result = $image->resize(800, 600);

    expect($result)->not->toBe($image);
});

test('rotate returns new instance', function () {
    $image = makeImage();
    $result = $image->rotate(90);

    expect($result)->not->toBe($image);
});

test('orient returns new instance', function () {
    $image = makeImage();
    $result = $image->orient();

    expect($result)->not->toBe($image);
});

test('blur returns new instance', function () {
    $image = makeImage();
    $result = $image->blur(10);

    expect($result)->not->toBe($image);
});

test('grayscale returns new instance', function () {
    $image = makeImage();
    $result = $image->grayscale();

    expect($result)->not->toBe($image);
});

test('optimize returns new instance', function () {
    $image = makeImage();
    $result = $image->optimize('webp');

    expect($result)->not->toBe($image);
});

test('quality returns new instance', function () {
    $image = makeImage();
    $result = $image->quality(80);

    expect($result)->not->toBe($image);
});

test('to webp returns new instance', function () {
    $image = makeImage();

    expect($image->toWebp())->not->toBe($image);
});

test('to jpg returns new instance', function () {
    $image = makeImage();

    expect($image->toJpg())->not->toBe($image);
});

test('to png returns new instance', function () {
    $image = makeImage();

    expect($image->toPng())->not->toBe($image);
});

test('to gif returns new instance', function () {
    $image = makeImage();

    expect($image->toGif())->not->toBe($image);
});

test('to avif returns new instance', function () {
    $image = makeImage();

    expect($image->toAvif())->not->toBe($image);
});

test('to heic returns new instance', function () {
    $image = makeImage();

    expect($image->toHeic())->not->toBe($image);
});

test('to jxl returns new instance', function () {
    $image = makeImage();

    expect($image->toJxl())->not->toBe($image);
});

test('to bmp returns new instance', function () {
    $image = makeImage();

    expect($image->toBmp())->not->toBe($image);
});

test('using returns new instance', function () {
    $image = makeImage();
    $result = $image->using('imagick');

    expect($result)->not->toBe($image);
});

test('original is not mutated', function () {
    $image = makeImage();
    $originalOptions = clone getOptions($image);

    $image->cover(100, 100)->optimize('webp');

    expect(getOptions($image))->toEqual($originalOptions);
});

test('chained operations accumulate', function () {
    $image = makeImage();
    $result = $image->cover(100, 100)->optimize('webp', 90)->blur(5);

    $options = getOptions($result);

    expect($options->coverWidth)->toBe(100);
    expect($options->coverHeight)->toBe(100);
    expect($options->format)->toBe('webp');
    expect($options->quality)->toBe(90);
    expect($options->blur)->toBe(5);
});

test('variants from same source are independent', function () {
    $image = makeImage();

    $thumb = $image->cover(100, 100);
    $large = $image->scale(800, 600);

    $thumbOptions = getOptions($thumb);
    $largeOptions = getOptions($large);

    expect($thumbOptions->coverWidth)->toBe(100);
    expect($thumbOptions->scaleWidth)->toBeNull();

    expect($largeOptions->coverWidth)->toBeNull();
    expect($largeOptions->scaleWidth)->toBe(800);
});

test('to bytes returns string', function () {
    $contents = fakeImageContents();
    $image = new Image($contents);

    expect($image->toBytes())->toBe($contents);
});

test('to bytes with closure', function () {
    $contents = fakeImageContents();
    $image = new Image(fn () => $contents);

    expect($image->toBytes())->toBe($contents);
});

test('closure is not called until to bytes', function () {
    $called = false;

    $image = new Image(function () use (&$called) {
        $called = true;

        return fakeImageContents();
    });

    expect($called)->toBeFalse();

    $image->toBytes();

    expect($called)->toBeTrue();
});

test('mime type detects jpeg', function () {
    $image = new Image(fakeImageContents());

    expect($image->mimeType())->toBe('image/jpeg');
});

test('extension returns jpg for jpeg', function () {
    $image = new Image(fakeImageContents());

    expect($image->extension())->toBe('jpg');
});

test('extension returns avif for avif', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('The imagick extension is not installed.');
    }

    $imagick = new \Imagick;
    $imagick->newImage(10, 10, 'red');
    $imagick->setImageFormat('avif');
    $contents = $imagick->getImageBlob();

    if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) !== 'image/avif') {
        $this->markTestSkipped('The installed fileinfo database does not recognize AVIF.');
    }

    $image = new Image($contents);

    expect($image->extension())->toBe('avif');
});

test('extension returns jxl for jxl', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('The imagick extension is not installed.');
    }

    if (\Imagick::queryFormats('JXL') === []) {
        $this->markTestSkipped('The Imagick extension was not compiled with jxl support.');
    }

    $imagick = new \Imagick;
    $imagick->newImage(10, 10, 'red');
    $imagick->setImageFormat('jxl');
    $contents = $imagick->getImageBlob();

    $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

    if (! in_array($mimeType, ['image/jxl', 'image/x-jxl'], true)) {
        $this->markTestSkipped('The installed fileinfo database does not recognize JXL.');
    }

    $image = new Image($contents);

    expect($image->extension())->toBe('jxl');
});

test('dimensions returns width and height', function () {
    $image = new Image(fakeImageContents(300, 200));

    expect($image->dimensions())->toBe([300, 200]);
});

test('dimensions throws when dimensions cannot be determined', function () {
    $image = new Image('not-an-image');

    $image->dimensions();
})->throws(ImageException::class, 'Unable to determine the dimensions of the image.');

test('dimensions defers to the driver for heic images', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', new Repository(['images' => ['default' => 'fake']]));

    $manager = new ImageManager($container);
    $manager->extend('fake', fn () => new class implements Driver
    {
        public function process(string $contents, ImagePipeline $pipeline): string
        {
            // Bytes that finfo reports as image/heic but getimagesizefromstring() cannot read.
            return "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic";
        }

        public function dimensions(string $contents): array
        {
            return [123, 45];
        }

        public function dominantColor(string $contents): string
        {
            return '#000000';
        }

        public function transformUsing(string $transformation, callable $callback): static
        {
            return $this;
        }
    });
    $container->registerInstance('image', $manager);

    ControlPanel::setInstance($container);

    try {
        $image = (new Image(fakeImageContents()))->using('fake')->cover(1, 1);

        expect($image->dimensions())->toBe([123, 45]);
        expect($image->width())->toBe(123);
        expect($image->height())->toBe(45);
    } finally {
        ControlPanel::setInstance(null);
    }
});

test('dimensions falls back to native reader when the driver cannot decode heic', function () {
    $container = new ControlPanel;
    $container->registerInstance('config', new Repository(['images' => ['default' => 'fake']]));

    $manager = new ImageManager($container);
    $manager->extend('fake', fn () => new class implements Driver
    {
        public function process(string $contents, ImagePipeline $pipeline): string
        {
            return "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic";
        }

        public function dimensions(string $contents): array
        {
            throw new ImageException('The driver cannot decode this image.');
        }

        public function dominantColor(string $contents): string
        {
            return '#000000';
        }

        public function transformUsing(string $transformation, callable $callback): static
        {
            return $this;
        }
    });
    $container->registerInstance('image', $manager);

    ControlPanel::setInstance($container);

    try {
        $image = (new Image(fakeImageContents()))->using('fake')->cover(1, 1);

        $image->dimensions();
    } finally {
        ControlPanel::setInstance(null);
    }
})->throws(ImageException::class, 'Unable to determine the dimensions of the image.');

test('hash name returns name with extension', function () {
    $image = new Image(fakeImageContents());

    $name = $image->hashName();

    expect($name)->toMatch('/^[a-zA-Z0-9]{40}\.jpg$/');
});

test('hash name with path', function () {
    $image = new Image(fakeImageContents());

    $name = $image->hashName('avatars');

    expect($name)->toStartWith('avatars/');
    expect($name)->toMatch('/^avatars\/[a-zA-Z0-9]{40}\.jpg$/');
});

test('file returns uploaded file when provided', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');
    $image = new Image(fn () => $file->getContent(), $file);

    expect($image->file())->toBe($file);
});

test('file returns null when not provided', function () {
    $image = new Image(fakeImageContents());

    expect($image->file())->toBeNull();
});

test('clone preserves uploaded file', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');
    $image = new Image(fn () => $file->getContent(), $file);

    $cloned = $image->cover(100, 100);

    expect($cloned->file())->toBe($file);
});

test('optimize has defaults', function () {
    $image = makeImage();
    $result = $image->optimize();

    $options = getOptions($result);

    expect($options->format)->toBe('webp');
    expect($options->quality)->toBe(70);
});

test('optimize throws for unsupported format', function () {
    $image = makeImage();

    $image->optimize('tiff');
})->throws(ImageException::class, 'The [tiff] format is not supported.');

test('quality sets option', function () {
    $image = makeImage();
    $result = $image->quality(60);

    expect(getOptions($result)->quality)->toBe(60);
});

test('to webp sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toWebp())->format)->toBe('webp');
});

test('to jpg sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toJpg())->format)->toBe('jpg');
});

test('to jpeg is alias for to jpg', function () {
    $image = makeImage();

    expect(getOptions($image->toJpeg())->format)->toBe('jpg');
});

test('to png sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toPng())->format)->toBe('png');
});

test('to gif sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toGif())->format)->toBe('gif');
});

test('to avif sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toAvif())->format)->toBe('avif');
});

test('to heic sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toHeic())->format)->toBe('heic');
});

test('to jxl sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toJxl())->format)->toBe('jxl');
});

test('to bmp sets format', function () {
    $image = makeImage();

    expect(getOptions($image->toBmp())->format)->toBe('bmp');
});

test('quality survives format conversion', function () {
    $image = makeImage();

    expect(getOptions($image->quality(50)->toJpg())->quality)->toBe(50);
    expect(getOptions($image->quality(90)->toWebp())->quality)->toBe(90);
});

test('format and quality can be set separately', function () {
    $image = makeImage();
    $result = $image->toWebp()->quality(60);

    $options = getOptions($result);

    expect($options->format)->toBe('webp');
    expect($options->quality)->toBe(60);
});

test('blur has default', function () {
    $image = makeImage();
    $result = $image->blur();

    expect(getOptions($result)->blur)->toBe(5);
});

test('sharpen returns new instance', function () {
    $image = makeImage();

    expect($image->sharpen())->not->toBe($image);
});

test('sharpen sets option', function () {
    $image = makeImage();

    expect(getOptions($image->sharpen(20))->sharpen)->toBe(20);
});

test('sharpen has default', function () {
    $image = makeImage();

    expect(getOptions($image->sharpen())->sharpen)->toBe(10);
});

test('flip vertically returns new instance', function () {
    $image = makeImage();

    expect($image->flipVertically())->not->toBe($image);
});

test('flip vertically sets option', function () {
    $image = makeImage();

    expect(getOptions($image->flipVertically())->flipVertically)->toBeTrue();
});

test('flip horizontally returns new instance', function () {
    $image = makeImage();

    expect($image->flipHorizontally())->not->toBe($image);
});

test('flip horizontally sets option', function () {
    $image = makeImage();

    expect(getOptions($image->flipHorizontally())->flipHorizontally)->toBeTrue();
});

test('width returns int', function () {
    $image = new Image(fakeImageContents(300, 200));

    expect($image->width())->toBe(300);
});

test('height returns int', function () {
    $image = new Image(fakeImageContents(300, 200));

    expect($image->height())->toBe(200);
});

test('to base64 returns encoded string', function () {
    $contents = fakeImageContents();
    $image = new Image($contents);

    expect($image->toBase64())->toBe(base64_encode($contents));
});

test('to data uri returns data uri', function () {
    $image = new Image(fakeImageContents());

    $dataUri = $image->toDataUri();

    expect($dataUri)->toStartWith('data:image/jpeg;base64,');
});

test('driver exception is wrapped in image exception', function () {
    $image = new Image(fakeImageContents());

    // Trigger a driver error by using a non-existent driver
    $image->using('nonexistent')->cover(100, 100)->toBytes();
})->throws(ImageException::class, 'Failed to process image:');

test('wrapped exception preserves original', function () {
    $image = new Image(fakeImageContents());

    try {
        $image->using('nonexistent')->cover(100, 100)->toBytes();
    } catch (ImageException $e) {
        expect($e->getPrevious())->not->toBeNull();

        return;
    }

    $this->fail('ImageException was not thrown.');
});

test('to bytes returns same result on multiple calls', function () {
    $image = new Image(fakeImageContents());

    $first = $image->toBytes();
    $second = $image->toBytes();

    expect($second)->toBe($first);
});

test('to bytes without operations returns original', function () {
    $contents = fakeImageContents();
    $image = new Image($contents);

    expect($image->toBytes())->toBe($contents);
});

test('has changes with only quality set', function () {
    $image = makeImage();
    $result = $image->quality(50);

    expect(getPipeline($result)->hasChanges())->toBeTrue();
});

test('clone does not share hash name cache', function () {
    $image = makeImage();
    $name1 = $image->hashName();

    $clone = $image->blur(1);
    $name2 = $image->hashName();

    // Same instance returns cached name
    expect($name2)->toBe($name1);
});

test('hash name is consistent on same instance', function () {
    $image = makeImage();

    expect($image->hashName())->toBe($image->hashName());
});

test('flip alias sets vertical option', function () {
    $image = makeImage();

    expect(getOptions($image->flip())->flipVertically)->toBeTrue();
});

test('flop alias sets horizontal option', function () {
    $image = makeImage();

    expect(getOptions($image->flop())->flipHorizontally)->toBeTrue();
});

test('flip vertically and horizontally together', function () {
    $image = makeImage();
    $result = $image->flipVertically()->flipHorizontally();

    expect(getOptions($result)->flipVertically)->toBeTrue();
    expect(getOptions($result)->flipHorizontally)->toBeTrue();
});

test('multiple operations chained', function () {
    $image = makeImage();
    $result = $image->orient()->cover(200, 200)->blur(10)->grayscale()->sharpen(5)->toWebp()->quality(75);

    $options = getOptions($result);

    expect($options->orient)->toBeTrue();
    expect($options->coverWidth)->toBe(200);
    expect($options->coverHeight)->toBe(200);
    expect($options->blur)->toBe(10);
    expect($options->grayscale)->toBeTrue();
    expect($options->sharpen)->toBe(5);
    expect($options->format)->toBe('webp');
    expect($options->quality)->toBe(75);
});

test('later operation overrides earlier', function () {
    $image = makeImage();
    $result = $image->cover(200, 200)->cover(100, 100);

    $options = getOptions($result);

    expect($options->coverWidth)->toBe(100);
    expect($options->coverHeight)->toBe(100);
});

test('extension returns bin for unknown mime', function () {
    $image = new Image('not-an-image');

    expect($image->extension())->toBe('bin');
});

test('file returns null for non upload', function () {
    $image = Image::class;
    $instance = new $image(fakeImageContents());

    expect($instance->file())->toBeNull();
});

test('using gd shortcut', function () {
    $image = makeImage();
    $result = $image->usingGd();

    $driver = (new \ReflectionProperty($result, 'driver'))->getValue($result);

    expect($driver)->toBe('gd');
});

test('using imagick shortcut', function () {
    $image = makeImage();
    $result = $image->usingImagick();

    $driver = (new \ReflectionProperty($result, 'driver'))->getValue($result);

    expect($driver)->toBe('imagick');
});

test('dimensions on tiny image', function () {
    $image = new Image(fakeImageContents(1, 1));

    expect($image->dimensions())->toBe([1, 1]);
    expect($image->width())->toBe(1);
    expect($image->height())->toBe(1);
});

test('to data uri contains valid base64', function () {
    $image = new Image(fakeImageContents());

    $dataUri = $image->toDataUri();
    $base64Part = substr($dataUri, strpos($dataUri, ',') + 1);

    expect(base64_decode($base64Part, true))->not->toBeFalse();
});

test('optimize throws for jpg with wrong spelling', function () {
    $image = makeImage();

    $image->optimize('jpge');
})->throws(ImageException::class, 'The [jpge] format is not supported.');

test('optimize allows png', function () {
    $result = makeImage()->optimize('png');

    expect(getOptions($result)->format)->toBe('png');
});

test('serialization throws exception', function () {
    $image = new Image(fakeImageContents());

    serialize($image);
})->throws(ImageException::class, 'Images cannot be serialized. Store the image first and serialize the path instead.');

test('image pipeline has no changes by default', function () {
    $pipeline = new ImagePipeline;

    expect($pipeline->hasChanges())->toBeFalse();
});

test('image pipeline has changes with zero quality', function () {
    $pipeline = new ImagePipeline;
    $pipeline->output->quality = 0;

    expect($pipeline->hasChanges())->toBeTrue();
});

test('image pipeline has changes with zero blur', function () {
    $pipeline = new ImagePipeline;
    $pipeline->add(new Blur(0));

    expect($pipeline->hasChanges())->toBeTrue();
});

test('image pipeline has changes with zero sharpen', function () {
    $pipeline = new ImagePipeline;
    $pipeline->add(new Sharpen(0));

    expect($pipeline->hasChanges())->toBeTrue();
});

test('image output options default quality constant', function () {
    expect(ImageOutputOptions::DEFAULT_QUALITY)->toBe(70);
});

test('cover sets both dimensions', function () {
    $image = makeImage();
    $result = $image->cover(300, 150);

    $options = getOptions($result);

    expect($options->coverWidth)->toBe(300);
    expect($options->coverHeight)->toBe(150);
});

test('scale sets both dimensions', function () {
    $image = makeImage();
    $result = $image->scale(1200, 800);

    $options = getOptions($result);

    expect($options->scaleWidth)->toBe(1200);
    expect($options->scaleHeight)->toBe(800);
});

test('contain sets dimensions and background', function () {
    $image = makeImage();
    $result = $image->contain(1200, 800, '#ffffff');

    $options = getOptions($result);

    expect($options->containWidth)->toBe(1200);
    expect($options->containHeight)->toBe(800);
    expect($options->containBackground)->toBe('#ffffff');
});

test('contain sets dominant background', function () {
    $image = makeImage();
    $result = $image->contain(1200, 800, 'dominant');

    $options = getOptions($result);

    expect($options->containWidth)->toBe(1200);
    expect($options->containHeight)->toBe(800);
    expect($options->containBackground)->toBe('dominant');
});

test('crop sets dimensions and position', function () {
    $image = makeImage();
    $result = $image->crop(300, 200, 10, 20);

    $options = getOptions($result);

    expect($options->cropWidth)->toBe(300);
    expect($options->cropHeight)->toBe(200);
    expect($options->cropX)->toBe(10);
    expect($options->cropY)->toBe(20);
});

test('resize sets both dimensions', function () {
    $image = makeImage();
    $result = $image->resize(1200, 800);

    $options = getOptions($result);

    expect($options->resizeWidth)->toBe(1200);
    expect($options->resizeHeight)->toBe(800);
});

test('resize sets width only', function () {
    $image = makeImage();
    $result = $image->resize(width: 1200);

    $options = getOptions($result);

    expect($options->resizeWidth)->toBe(1200);
    expect($options->resizeHeight)->toBeNull();
});

test('resize sets height only', function () {
    $image = makeImage();
    $result = $image->resize(height: 800);

    $options = getOptions($result);

    expect($options->resizeWidth)->toBeNull();
    expect($options->resizeHeight)->toBe(800);
});

test('resize requires at least one dimension', function () {

    makeImage()->resize();
})->throws(ImageException::class, 'At least one resize dimension must be specified.');

test('rotate sets angle and background', function () {
    $image = makeImage();
    $result = $image->rotate(90, '#ffffff');

    $options = getOptions($result);

    expect($options->rotateAngle)->toBe(90.0);
    expect($options->rotateBackground)->toBe('#ffffff');
});

test('rotate sets dominant background', function () {
    $image = makeImage();
    $result = $image->rotate(45, 'dominant');

    $options = getOptions($result);

    expect($options->rotateAngle)->toBe(45.0);
    expect($options->rotateBackground)->toBe('dominant');
});

test('scale sets width only', function () {
    $image = makeImage();
    $result = $image->scale(width: 1200);

    $options = getOptions($result);

    expect($options->scaleWidth)->toBe(1200);
    expect($options->scaleHeight)->toBeNull();
});

test('scale sets height only', function () {
    $image = makeImage();
    $result = $image->scale(height: 800);

    $options = getOptions($result);

    expect($options->scaleWidth)->toBeNull();
    expect($options->scaleHeight)->toBe(800);
});

test('scale requires at least one dimension', function () {

    makeImage()->scale();
})->throws(ImageException::class, 'At least one scale dimension must be specified.');

test('orient sets option', function () {
    $image = makeImage();
    $result = $image->orient();

    expect(getOptions($result)->orient)->toBeTrue();
});

test('optimize sets both format and quality', function () {
    $image = makeImage();
    $result = $image->optimize('jpg', 90);

    $options = getOptions($result);

    expect($options->format)->toBe('jpg');
    expect($options->quality)->toBe(90);
});

test('optimize allows gif', function () {
    $result = makeImage()->optimize('gif');

    expect(getOptions($result)->format)->toBe('gif');
});

test('optimize allows avif', function () {
    $result = makeImage()->optimize('avif');

    expect(getOptions($result)->format)->toBe('avif');
});

test('optimize allows heic', function () {
    $result = makeImage()->optimize('heic');

    expect(getOptions($result)->format)->toBe('heic');
});

test('optimize allows jxl', function () {
    $result = makeImage()->optimize('jxl');

    expect(getOptions($result)->format)->toBe('jxl');
});

test('optimize normalizes heif to heic', function () {
    $result = makeImage()->optimize('heif');

    expect(getOptions($result)->format)->toBe('heic');
});

test('optimize allows jpeg spelling', function () {
    $image = makeImage();
    $result = $image->optimize('jpeg', 90);

    expect(getOptions($result)->format)->toBe('jpeg');
});

test('scale does not set cover', function () {
    $image = makeImage();
    $result = $image->scale(800, 600);

    $options = getOptions($result);

    expect($options->coverWidth)->toBeNull();
    expect($options->coverHeight)->toBeNull();
});

test('cover does not set scale', function () {
    $image = makeImage();
    $result = $image->cover(200, 200);

    $options = getOptions($result);

    expect($options->scaleWidth)->toBeNull();
    expect($options->scaleHeight)->toBeNull();
});

test('three variants from same source', function () {
    $image = makeImage();

    $a = $image->cover(100, 100);
    $b = $image->scale(800, 600);
    $c = $image->blur(10);

    expect(getOptions($a)->coverWidth)->toBe(100);
    expect(getOptions($a)->scaleWidth)->toBeNull();
    expect(getOptions($a)->blur)->toBeNull();

    expect(getOptions($b)->coverWidth)->toBeNull();
    expect(getOptions($b)->scaleWidth)->toBe(800);
    expect(getOptions($b)->blur)->toBeNull();

    expect(getOptions($c)->coverWidth)->toBeNull();
    expect(getOptions($c)->scaleWidth)->toBeNull();
    expect(getOptions($c)->blur)->toBe(10);
});

test('clone resets processed flag', function () {
    $image = makeImage();
    $clone = $image->cover(100, 100);

    $processed = (new \ReflectionProperty($clone, 'processed'))->getValue($clone);

    expect($processed)->toBeFalse();
});

test('using sets driver string', function () {
    $image = makeImage();
    $result = $image->using('custom-driver');

    $driver = (new \ReflectionProperty($result, 'driver'))->getValue($result);

    expect($driver)->toBe('custom-driver');
});

test('implements stringable', function () {
    $image = new Image(fakeImageContents());

    expect($image)->toBeInstanceOf(\Stringable::class);
});

test('to string returns data uri', function () {
    $image = new Image(fakeImageContents());

    expect($image->toString())->toBe($image->toDataUri());
});

test('magic to string returns data uri', function () {
    $image = new Image(fakeImageContents());

    expect((string) $image)->toBe($image->toDataUri());
});

test('image exception extends runtime exception', function () {
    $exception = new ImageException('test');

    expect($exception)->toBeInstanceOf(\RuntimeException::class);
});

test('implements responsable', function () {
    $image = new Image(fakeImageContents());

    expect($image)->toBeInstanceOf(\Mariner\Contracts\NutsAndBolts\Responsable::class);
});

test('to response returns response with image bytes', function () {
    $contents = fakeImageContents();
    $image = new Image($contents);

    $response = $image->toResponse(new \Mariner\Http\Request);

    expect($response)->toBeInstanceOf(\Mariner\Http\Response::class);
    expect($response->getContent())->toBe($contents);
    expect($response->getStatusCode())->toBe(200);
});

test('to response sets content type header', function () {
    $image = new Image(fakeImageContents());

    $response = $image->toResponse(new \Mariner\Http\Request);

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
});

function makeImage(): Image
{
    return new Image(fakeImageContents());
}

function fakeImageContents(int $width = 100, int $height = 100): string
{
    $file = UploadedFile::fake()->image('test.jpg', $width, $height);

    return file_get_contents($file->getRealPath());
}

function getOptions(Image $image): object
{
    $pipeline = (new \ReflectionProperty($image, 'pipeline'))->getValue($image);

    $options = (object) [
        'coverWidth' => null,
        'coverHeight' => null,
        'containWidth' => null,
        'containHeight' => null,
        'containBackground' => null,
        'cropWidth' => null,
        'cropHeight' => null,
        'cropX' => null,
        'cropY' => null,
        'resizeWidth' => null,
        'resizeHeight' => null,
        'rotateAngle' => null,
        'rotateBackground' => null,
        'scaleWidth' => null,
        'scaleHeight' => null,
        'orient' => null,
        'blur' => null,
        'grayscale' => null,
        'sharpen' => null,
        'flipVertically' => null,
        'flipHorizontally' => null,
        'format' => $pipeline->output->format,
        'quality' => $pipeline->output->quality,
    ];

    foreach ($pipeline->transformations as $transformation) {
        match (true) {
            $transformation instanceof Cover => [$options->coverWidth, $options->coverHeight] = [$transformation->width, $transformation->height],
            $transformation instanceof Contain => [$options->containWidth, $options->containHeight, $options->containBackground] = [$transformation->width, $transformation->height, $transformation->background],
            $transformation instanceof Crop => [$options->cropWidth, $options->cropHeight, $options->cropX, $options->cropY] = [$transformation->width, $transformation->height, $transformation->x, $transformation->y],
            $transformation instanceof Resize => [$options->resizeWidth, $options->resizeHeight] = [$transformation->width, $transformation->height],
            $transformation instanceof Rotate => [$options->rotateAngle, $options->rotateBackground] = [$transformation->angle, $transformation->background],
            $transformation instanceof Scale => [$options->scaleWidth, $options->scaleHeight] = [$transformation->width, $transformation->height],
            $transformation instanceof Orient => $options->orient = true,
            $transformation instanceof Blur => $options->blur = $transformation->amount,
            $transformation instanceof Grayscale => $options->grayscale = true,
            $transformation instanceof Sharpen => $options->sharpen = $transformation->amount,
            $transformation instanceof FlipVertically => $options->flipVertically = true,
            $transformation instanceof FlipHorizontally => $options->flipHorizontally = true,
            default => null,
        };
    }

    return $options;
}

function getPipeline(Image $image): ImagePipeline
{
    return (new \ReflectionProperty($image, 'pipeline'))->getValue($image);
}
