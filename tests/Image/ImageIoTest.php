<?php

use Mariner\Image\ImageException;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-image-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    $this->app['config']->set('filesystems.disks.pictures', ['driver' => 'local', 'root' => $this->dir]);
    file_put_contents($this->dir.'/source.bin', 'image bytes');
    $this->images = $this->app['image'];
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

// Thread workers need ext-parallel; without it, null picks process workers.
dataset('pools', array_merge([null, 'process'], extension_loaded('parallel') ? ['thread'] : []));

it('loads from storage and a path, and stores, through the pool', function (?string $pool) {
    $images = $this->images->via($pool);

    $path = $images->fromStorage('source.bin', 'pictures')->storeAs('copies', 'from-storage.bin', 'pictures');
    $images->fromPath($this->dir.'/source.bin')->storeAs('copies', 'from-path.bin', 'pictures');

    expect($path)->toBe('copies/from-storage.bin')
        ->and(file_get_contents($this->dir.'/copies/from-storage.bin'))->toBe('image bytes')
        ->and(file_get_contents($this->dir.'/copies/from-path.bin'))->toBe('image bytes');
})->with('pools');

it('leaves the shared manager and its images in place', function () {
    $this->images->via();

    $image = $this->images->fromBytes('raw');

    expect((fn () => $this->offloaded)->call($this->images))->toBeFalse()
        ->and((fn () => $this->offloaded)->call($image))->toBeFalse()
        ->and((fn () => $this->offloaded)->call($this->images->via()->fromBytes('raw')))->toBeTrue();
});

/** A PNG of the given size, drawn with GD. */
function png(int $width, int $height): string
{
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 200, 40, 40));
    ob_start();
    imagepng($canvas);

    return ob_get_clean();
}

it('resizes on the pool, matching the in-place result', function (?string $pool) {
    $source = png(64, 32);

    $offloaded = $this->images->via($pool)->fromBytes($source)->resize(16, 8)->toPng();
    $inPlace = $this->images->fromBytes($source)->resize(16, 8)->toPng();

    expect($offloaded->dimensions())->toBe([16, 8])
        ->and($offloaded->mimeType())->toBe('image/png')
        ->and($offloaded->toBytes())->toBe($inPlace->toBytes());
})->with('pools');

it('resizes on the pool without building a driver here', function () {
    $this->images->via()->fromBytes(png(64, 32))->resize(16, 8)->toBytes();

    expect($this->images->getDrivers())->toBe([]);
});

it('raises the worker\'s processing failure as an ImageException', function () {
    // The pipeline runs on the worker, where the driver cannot decode the bytes.
    expect(fn () => $this->images->via()->fromBytes('not an image')->resize(10, 10)->toBytes())
        ->toThrow(ImageException::class, 'Failed to process image');
});

it('refuses drivers a worker never receives', function () {
    $this->images->extend('custom', fn () => null);
    $this->images->transformUsing('gd', 'resize', fn () => null);

    expect(fn () => $this->images->via()->fromBytes('x')->using('custom')->resize(10, 10)->toBytes())
        ->toThrow(ImageException::class, 'registered with extend()')
        ->and(fn () => $this->images->via()->fromBytes('x')->using('gd')->resize(10, 10)->toBytes())
        ->toThrow(ImageException::class, 'runtime transformation handlers');
});

it('lets two loop tasks overlap their offloaded loads', function () {
    $loop = $this->app['event-loop'];
    $events = [];
    $load = function (string $name) use ($loop, &$events) {
        $images = $this->images->via();

        return $loop->async(function () use ($images, $name, &$events) {
            $events[] = "{$name}:start";
            $bytes = $images->fromStorage('source.bin', 'pictures')->toBytes();
            $events[] = "{$name}:done";

            return $bytes;
        });
    };

    $a = $load('a');
    $b = $load('b');

    expect([$loop->await($a), $loop->await($b)])->toBe(['image bytes', 'image bytes'])
        ->and(array_search('b:start', $events))->toBeLessThan(array_search('a:done', $events));
});
