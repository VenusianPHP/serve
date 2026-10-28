<?php

namespace Venusian\Serve\Tests\Image\Drivers;

use Mariner\Contracts\Image\Transformation;
use Voyager\Filesystem\UploadedFile;
use Mariner\Image\Drivers\GdDriver;
use Mariner\Image\ImageException;
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
use Intervention\Image\Interfaces\ImageInterface;

test('processes cover', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(200, 200);

    $pipeline = pipeline(new Cover(100, 50));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(50);
});

test('processes optimize to webp', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'webp');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_WEBP);
});

test('processes optimize to jpeg', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'jpg');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_JPEG);
});

test('processes optimize to png', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'png');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_PNG);
});

test('processes optimize to gif', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'gif');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_GIF);
});

test('processes optimize to avif', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'avif');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_AVIF);
});

test('processes avif input', function () {
    $driver = new GdDriver;
    $contents = $driver->process(fakeImageContents(), pipeline(format: 'avif'));

    $result = $driver->process($contents, pipeline(new Cover(50, 25), format: 'jpg'));

    expect(array_slice(getimagesizefromstring($result), 0, 2))->toBe([50, 25]);
});

test('processes optimize to bmp', function () {
    $driver = new GdDriver;

    $pipeline = pipeline(format: 'bmp');

    $result = $driver->process(fakeImageContents(), $pipeline);

    expect(getimagesizefromstring($result)[2])->toBe(IMAGETYPE_BMP);
});

test('processes cover and optimize together', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(300, 300);

    $pipeline = pipeline(new Cover(75, 75), format: 'webp');

    $result = $driver->process($contents, $pipeline);

    [$width, $height, $type] = getimagesizefromstring($result);

    expect($width)->toBe(75);
    expect($height)->toBe(75);
    expect($type)->toBe(IMAGETYPE_WEBP);
});

test('processes contain', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Contain(200, 200, '#ffffff'));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('processes contain with dominant background', function () {
    $driver = new GdDriver;
    $contents = solidColorImageContents(255, 0, 0, 400, 200);

    $pipeline = pipeline(new Contain(200, 200, 'dominant'));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('dominant color returns hex for solid image', function () {
    $driver = new GdDriver;
    $contents = solidColorImageContents(0, 128, 255);

    expect($driver->dominantColor($contents))->toBe('#0080ff');
});

test('dominant color ignores alpha channel', function () {
    $driver = new GdDriver;
    $contents = semiTransparentColorImageContents(0, 128, 255, 128);

    expect($driver->dominantColor($contents))->toBe('#0080ff');
});

test('processes crop', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Crop(100, 50, 10, 20));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(50);
});

test('processes resize', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Resize(200, 200));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(200);
});

test('processes rotate', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 50);

    $pipeline = pipeline(new Rotate(90));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(50);
    expect($height)->toBe(100);
});

test('processes rotate with dominant background', function () {
    $driver = new GdDriver;
    $contents = solidColorImageContents(0, 255, 0, 100, 50);

    $pipeline = pipeline(new Rotate(45, 'dominant'));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect(getimagesizefromstring($result))->not->toBeFalse();
});

test('processes scale', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Scale(200, 200));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('processes scale width only', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Scale(200, null));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('processes scale height only', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(400, 200);

    $pipeline = pipeline(new Scale(null, 100));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(200);
    expect($height)->toBe(100);
});

test('scale does not upscale', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 80);

    $pipeline = pipeline(new Scale(800, 600));

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(80);
});

test('format conversion preserves dimensions', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(300, 200);

    $pipeline = pipeline(format: 'webp');

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(300);
    expect($height)->toBe(200);
});

test('quality preserves dimensions', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(300, 200);

    $pipeline = pipeline(quality: 50);

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(300);
    expect($height)->toBe(200);
});

test('processes orient', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new Orient);

    $result = $driver->process($contents, $pipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(100);
});

test('processes blur', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new Blur(10));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes grayscale', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new Grayscale);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes sharpen', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new Sharpen(10));

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
    expect($result)->not->toBe($contents);
});

test('processes flip vertically', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new FlipVertically);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
});

test('processes flip horizontally', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $pipeline = pipeline(new FlipHorizontally);

    $result = $driver->process($contents, $pipeline);

    expect($result)->not->toBeEmpty();
});

test('processes custom transformation', function () {
    $driver = new GdDriver;
    $transformation = new class implements Transformation {
        //
    };
    $received = null;

    $driver->transformUsing($transformation::class, function (ImageInterface $image, Transformation $transformation) use (&$received) {
        $received = $transformation;

        return $image->scaleDown(50, 50);
    });

    $result = $driver->process(fakeImageContents(100, 100), pipeline($transformation));

    [$width, $height] = getimagesizefromstring($result);

    expect($received)->toBe($transformation);
    expect($width)->toBe(50);
    expect($height)->toBe(50);
});

test('throws for unsupported input format', function () {
    $driver = new GdDriver;

    $driver->process('not-an-image', new ImagePipeline);
})->throws(ImageException::class, 'The image format [text/plain] is not supported.');

test('returns image without options', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $result = $driver->process($contents, new ImagePipeline);

    [$width, $height] = getimagesizefromstring($result);

    expect($width)->toBe(100);
    expect($height)->toBe(100);
});

test('quality affects file size', function () {
    $driver = new GdDriver;
    $contents = fakeImageContents(100, 100);

    $lowQuality = pipeline(format: 'jpg', quality: 1);
    $highQuality = pipeline(format: 'jpg', quality: 100);

    $lowResult = $driver->process($contents, $lowQuality);
    $highResult = $driver->process($contents, $highQuality);

    expect(strlen($lowResult))->toBeLessThan(strlen($highResult));
});

test('ensure requirements passes', function () {
    $driver = new GdDriver;

    $driver->ensureRequirementsAreMet();

    expect(true)->toBeTrue();
});

test('dimensions returns the decoded size', function () {
    $driver = new GdDriver;
    $contents = $driver->process(fakeImageContents(320, 240), pipeline(new Cover(200, 150), format: 'png'));

    expect($driver->dimensions($contents))->toBe([200, 150]);
});

function fakeImageContents(int $width = 100, int $height = 100): string
{
    $file = UploadedFile::fake()->image('test.jpg', $width, $height);

    return file_get_contents($file->getRealPath());
}

function solidColorImageContents(int $red, int $green, int $blue, int $width = 100, int $height = 100): string
{
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, $red, $green, $blue);
    imagefill($image, 0, 0, $color);

    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function semiTransparentColorImageContents(int $red, int $green, int $blue, int $alpha, int $width = 100, int $height = 100): string
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    // GD alpha runs 0 (opaque) to 127 (fully transparent), the inverse of a 0-255 alpha channel.
    $gdAlpha = (int) round((255 - $alpha) / 255 * 127);
    $color = imagecolorallocatealpha($image, $red, $green, $blue, $gdAlpha);
    imagefill($image, 0, 0, $color);

    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function pipeline(?Transformation $transformation = null, ?string $format = null, ?int $quality = null): ImagePipeline
{
    $pipeline = new ImagePipeline;

    if ($transformation) {
        $pipeline->add($transformation);
    }

    $pipeline->output->format = $format;
    $pipeline->output->quality = $quality;

    return $pipeline;
}
