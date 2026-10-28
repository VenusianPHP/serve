<?php

namespace Venusian\Serve\Tests\Image\Drivers;

use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Image\Transformation;
use Voyager\Filesystem\UploadedFile;
use Mariner\Image\Drivers\ImagickDriver;
use Mariner\Image\Image;
use Mariner\Image\ImageManager;
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

beforeEach(function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('The imagick extension is not installed.');
    }
});

test('processes cover', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(200, 200);

    $pipeline = imagickPipeline(new Cover(100, 50));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(50);
});

test('processes optimize to webp', function () {
    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'webp');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_WEBP);
});

test('processes optimize to jpeg', function () {
    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'jpg');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_JPEG);
});

test('processes optimize to png', function () {
    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'png');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_PNG);
});

test('processes optimize to gif', function () {
    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'gif');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_GIF);
});

test('processes optimize to avif', function () {
    ensureImageFormatCanBeEncoded('avif');

    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'avif');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    // PHP's getimagesizefromstring()/finfo AVIF detection varies by the
    // libavif/libmagic versions installed, so we assert on the ISOBMFF
    // "ftyp" box and brand directly instead of relying on either.
    expect(substr($result, 0, 16))->toContain('ftyp');
    expect(substr($result, 0, 32))->toMatch('/avif|avis|mif1/');
});

test('processes avif input', function () {
    ensureImageFormatCanBeEncoded('avif');

    $driver = new ImagickDriver;
    $contents = $driver->process(imagickFakeImageContents(), imagickPipeline(format: 'avif'));

    $result = $driver->process($contents, imagickPipeline(new Cover(50, 25), format: 'jpg'));

    expect(array_slice(getimagesizefromstring($result), 0, 2))->toBe([50, 25]);
});

test('processes optimize to heic', function () {
    ensureImageFormatCanBeEncoded('heic');

    $driver = new ImagickDriver;

    $result = $driver->process(imagickFakeImageContents(), imagickPipeline(format: 'heic'));

    expect(substr($result, 0, 16))->toContain('ftyp');
    expect(substr($result, 0, 32))->toMatch('/heic|heix|hevc|hevx|mif1/');
});

test('processes heic input', function () {
    ensureImageFormatCanBeEncoded('heic');

    $driver = new ImagickDriver;
    $contents = $driver->process(imagickFakeImageContents(), imagickPipeline(format: 'heic'));

    $result = $driver->process($contents, imagickPipeline(new Cover(50, 25)));

    expect(substr($result, 0, 16))->toContain('ftyp');
    expect(substr($result, 0, 32))->toMatch('/heic|heix|hevc|hevx|mif1/');

    $result = $driver->process($result, imagickPipeline(format: 'jpg'));

    expect(array_slice(getimagesizefromstring($result), 0, 2))->toBe([50, 25]);
});

test('processes optimize to jxl', function () {
    ensureImageFormatCanBeEncoded('jxl');

    $driver = new ImagickDriver;

    $result = $driver->process(imagickFakeImageContents(), imagickPipeline(format: 'jxl'));

    $header = substr($result, 0, 16);

    expect(str_starts_with($result, "\xff\x0a") || str_contains($header, 'JXL '))->toBeTrue();
});

test('processes jxl input', function () {
    ensureImageFormatCanBeEncoded('jxl');

    $driver = new ImagickDriver;
    $contents = $driver->process(imagickFakeImageContents(), imagickPipeline(format: 'jxl'));

    $result = $driver->process($contents, imagickPipeline(new Cover(50, 25)));

    $header = substr($result, 0, 16);

    expect(str_starts_with($result, "\xff\x0a") || str_contains($header, 'JXL '))->toBeTrue();

    $result = $driver->process($result, imagickPipeline(format: 'jpg'));

    expect(array_slice(getimagesizefromstring($result), 0, 2))->toBe([50, 25]);
});

test('dimensions returns the decoded size', function () {
    $driver = new ImagickDriver;
    $contents = $driver->process(imagickFakeImageContents(320, 240), imagickPipeline(new Cover(200, 150), format: 'png'));

    expect($driver->dimensions($contents))->toBe([200, 150]);
});

test('dimensions returns the display size for heic', function () {
    ensureImageFormatCanBeEncoded('heic');

    $driver = new ImagickDriver;

    // 137x73 is a size where HEIC's coded/padded frame differs from the display size, which
    // getimagesize() misreports; the driver must return the true display size.
    $contents = $driver->process(imagickFakeImageContents(400, 300), imagickPipeline(new Cover(137, 73), format: 'heic'));

    expect($driver->dimensions($contents))->toBe([137, 73]);
});

test('image dimensions are correct for real heic through public api', function () {
    ensureImageFormatCanBeEncoded('heic');

    // End-to-end through the public Image API on a real HEIC; fails pre-fix (getimagesize returns 138x74).
    $heic = (new ImagickDriver)->process(
        imagickFakeImageContents(400, 300),
        imagickPipeline(new Cover(137, 73), format: 'heic')
    );

    $container = new ControlPanel;
    $container->registerInstance('config', new Repository(['images' => ['default' => 'imagick']]));
    $container->registerInstance('image', new ImageManager($container));
    ControlPanel::setInstance($container);

    try {
        expect((new Image($heic))->dimensions())->toBe([137, 73]);
    } finally {
        ControlPanel::setInstance(null);
    }
});

test('processes optimize to bmp', function () {
    $driver = new ImagickDriver;

    $pipeline = imagickPipeline(format: 'bmp');

    $result = $driver->process(imagickFakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_BMP);
});

test('processes cover and optimize together', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(300, 300);

    $pipeline = imagickPipeline(new Cover(75, 75), format: 'webp');

    $result = $driver->process($contents, $pipeline);

    [$width, $height, $type] = getimagesizefromstring($result);

    expect($width)->toBe(75);
    expect($height)->toBe(75);
    expect($type)->toBe(IMAGETYPE_WEBP);
});

test('processes contain', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Contain(200, 200, '#ffffff'));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('processes contain with dominant background', function () {
    $driver = new ImagickDriver;
    $contents = imagickSolidColorImageContents(255, 0, 0, 400, 200);

    $pipeline = imagickPipeline(new Contain(200, 200, 'dominant'));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('dominant color returns hex for solid image', function () {
    $driver = new ImagickDriver;
    $contents = imagickSolidColorImageContents(0, 128, 255);

    expect($driver->dominantColor($contents))->toBe('#0080ff');
});

test('dominant color ignores alpha channel', function () {
    $driver = new ImagickDriver;
    $contents = imagickSemiTransparentColorImageContents(0, 128, 255, 128);

    expect($driver->dominantColor($contents))->toBe('#0080ff');
});

test('processes crop', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Crop(100, 50, 10, 20));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(50);
});

test('processes resize', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Resize(200, 200));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('processes rotate', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 50);

    $pipeline = imagickPipeline(new Rotate(90));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(50);
    expect($height)->toBe(100);
});

test('processes rotate with dominant background', function () {
    $driver = new ImagickDriver;
    $contents = imagickSolidColorImageContents(0, 255, 0, 100, 50);

    $pipeline = imagickPipeline(new Rotate(45, 'dominant'));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect(getimagesizefromstring($result))->not->toBeFalse();
});

test('processes scale', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Scale(200, 200));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('processes scale width only', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Scale(200, null));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('processes scale height only', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(400, 200);

    $pipeline = imagickPipeline(new Scale(null, 100));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('scale does not upscale', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 80);

    $pipeline = imagickPipeline(new Scale(800, 600));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(80);
});

test('format conversion preserves dimensions', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(300, 200);

    $pipeline = imagickPipeline(format: 'webp');

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(300);
    expect($height)->toBe(200);
});

test('quality preserves dimensions', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(300, 200);

    $pipeline = imagickPipeline(quality: 50);

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(300);
    expect($height)->toBe(200);
});

test('processes orient', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new Orient);

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(100);
});

test('processes blur', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new Blur(10));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes grayscale', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new Grayscale);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes sharpen', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new Sharpen(10));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes flip vertically', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new FlipVertically);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
});

test('processes flip horizontally', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $pipeline = imagickPipeline(new FlipHorizontally);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
});

test('returns image without options', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $result = $driver->process($contents, new ImagePipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(100);
});

test('quality affects file size', function () {
    $driver = new ImagickDriver;
    $contents = imagickFakeImageContents(100, 100);

    $lowQuality = imagickPipeline(format: 'jpg', quality: 1);
    $highQuality = imagickPipeline(format: 'jpg', quality: 100);

    $lowResult = $driver->process($contents, $lowQuality);
    $highResult = $driver->process($contents, $highQuality);

    expect(strlen($lowResult))->toBeLessThan(strlen($highResult));
});

test('ensure requirements passes', function () {
    $driver = new ImagickDriver;

    $driver->ensureRequirementsAreMet();

    expect(true)->toBeTrue();
});

function imagickFakeImageContents(int $width = 100, int $height = 100): string
{
    $file = UploadedFile::fake()->image('test.jpg', $width, $height);

    return file_get_contents($file->getRealPath());
}

function ensureImageFormatCanBeEncoded(string $format): void
{
    if (\Imagick::queryFormats(strtoupper($format)) === []) {
        $this->markTestSkipped("The Imagick extension was not compiled with {$format} support.");
    }

    try {
        $imagick = new \Imagick;
        $imagick->newImage(1, 1, 'white');
        $imagick->setImageFormat($format);
        $encoded = $imagick->getImageBlob();
    } catch (\ImagickException) {
        // Some builds ship the HEIC decode delegate but no encoder, in which case encoding
        // raises instead of returning an empty blob. Either way, encoding is unavailable.
        $encoded = '';
    }

    if ($encoded === '') {
        $this->markTestSkipped("The Imagick extension cannot encode {$format} images.");
    }
}

function imagickSolidColorImageContents(int $red, int $green, int $blue, int $width = 100, int $height = 100): string
{
    $imagick = new \Imagick;
    $imagick->newImage($width, $height, new \ImagickPixel(sprintf('rgb(%d,%d,%d)', $red, $green, $blue)));
    $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
    $imagick->setImageFormat('png');

    $contents = $imagick->getImageBlob();
    $imagick->clear();
    $imagick->destroy();

    return $contents;
}

function imagickSemiTransparentColorImageContents(int $red, int $green, int $blue, int $alpha, int $width = 100, int $height = 100): string
{
    $imagick = new \Imagick;
    $imagick->newImage($width, $height, new \ImagickPixel(sprintf('rgba(%d,%d,%d,%.2f)', $red, $green, $blue, $alpha / 255)));
    $imagick->setImageFormat('png');

    $contents = $imagick->getImageBlob();
    $imagick->clear();
    $imagick->destroy();

    return $contents;
}

function imagickPipeline(?Transformation $transformation = null, ?string $format = null, ?int $quality = null): ImagePipeline
{
    $pipeline = new ImagePipeline;

    if ($transformation) {
        $pipeline->add($transformation);
    }

    $pipeline->output->format = $format;
    $pipeline->output->quality = $quality;

    return $pipeline;
}
