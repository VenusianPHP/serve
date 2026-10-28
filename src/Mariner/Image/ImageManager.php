<?php

namespace Mariner\Image;

use BackedEnum;
use Voyager\Contracts\Filesystem\Factory as FilesystemFactory;
use Mariner\Contracts\Image\Driver;
use Voyager\Filesystem\Filesystem;
use Voyager\Http\Client\Factory as HttpFactory;
use Voyager\Filesystem\UploadedFile;
use Mariner\Image\Drivers\GdDriver;
use Mariner\Image\Drivers\ImagickDriver;
use Voyager\NutsAndBolts\Manager;
use InvalidArgumentException;

use function Voyager\NutsAndBolts\enum_value;

class ImageManager extends Manager
{
    /**
     * The registered transformation handlers.
     *
     * @var array<string, array<class-string<\Mariner\Contracts\Image\Transformation>, callable>>
     */
    protected array $transformationHandlers = [];

    /**
     * Create an image instance from raw bytes.
     */
    /**
     * Whether images load, process and store through a worker pool, and which one.
     */
    protected bool $offloaded = false;

    protected ?string $pool = null;

    /**
     * A copy whose images load their sources, run their pipelines and store through a worker pool,
     * each waited on in place: inside a loop task, the waits leave the loop free.
     *
     * @param  string|null  $pool  'thread' or 'process'; null takes the framework's choice
     */
    public function via(?string $pool = null): static
    {
        $manager = clone $this;
        $manager->offloaded = true;
        $manager->pool = $pool;

        return $manager;
    }

    /**
     * Why a worker cannot run the driver as configured here, or null when it can: custom drivers
     * (extend()) and runtime transformation handlers are closures, which a worker never receives.
     */
    public function workerRefusal(string $driver): ?string
    {
        return match (true) {
            isset($this->customCreators[$driver]) => "Image driver [{$driver}] was registered with extend(), which a worker never receives",
            ! empty($this->transformationHandlers[$driver]) => "Image driver [{$driver}] has runtime transformation handlers, which a worker never receives",
            default => null,
        };
    }

    public function fromBytes(string $contents): Image
    {
        return $this->made(new Image($contents));
    }

    /**
     * Give an image made here this manager's pool, when it has one.
     */
    protected function made(Image $image): Image
    {
        return $this->offloaded ? $image->via($this->pool) : $image;
    }

    /**
     * Create an image instance from a stream.
     *
     * @param  resource  $stream
     */
    public function fromStream(mixed $stream): Image
    {
        return $this->made(new Image(
            fn () => stream_get_contents($stream) ?: throw new ImageException('Invalid stream image data.'),
        ));
    }

    /**
     * Create an image instance from a base64 encoded string.
     */
    public function fromBase64(string $base64): Image
    {
        return $this->made(new Image(
            fn () => base64_decode($base64, true) ?: throw new ImageException('Invalid base64 image data.'),
        ));
    }

    /**
     * Create an image instance from a file path.
     */
    public function fromPath(string $path): Image
    {
        return $this->made(new Image(
            fn () => $this->offloaded
                ? $this->vessel->make(Filesystem::class)->via($this->pool)->get($path)->wait()
                : $this->vessel->make(Filesystem::class)->get($path),
        ));
    }

    /**
     * Create an image instance from a storage disk path.
     */
    public function fromStorage(string $path, BackedEnum|string|null $disk = null): Image
    {
        $disk = enum_value($disk);

        return $this->made(new Image(
            fn () => $this->offloaded
                ? $this->vessel->make(FilesystemFactory::class)->disk($disk)->via($this->pool)->get($path)->wait()
                : $this->vessel->make(FilesystemFactory::class)->disk($disk)->get($path),
        ));
    }

    /**
     * Create an image instance from an uploaded file.
     */
    public function fromUpload(UploadedFile $file): Image
    {
        return $this->made(new Image(
            fn () => $this->offloaded
                ? $this->vessel->make(Filesystem::class)->via($this->pool)->get($file->getRealPath())->wait()
                : $file->getContent(),
            $file,
        ));
    }

    /**
     * Create an image instance from a URL.
     */
    public function fromUrl(string $url): Image
    {
        return $this->made(new Image(
            fn () => ($this->offloaded
                ? $this->vessel->make(HttpFactory::class)->async()->get($url)->wait()
                : $this->vessel->make(HttpFactory::class)->get($url))
                ->throw()
                ->body(),
        ));
    }

    /**
     * Create a new driver instance.
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver($driver): Driver
    {
        try {
            $instance = parent::createDriver($driver);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("Image driver [{$driver}] is not supported.", 0, $e);
        }

        if (method_exists($instance, 'ensureRequirementsAreMet')) {
            $instance->ensureRequirementsAreMet();
        }

        $this->applyTransformationHandlers($driver, $instance);

        return $instance;
    }

    /**
     * Create the GD image driver.
     */
    protected function createGdDriver(): GdDriver
    {
        return new GdDriver;
    }

    /**
     * Create the Imagick image driver.
     */
    protected function createImagickDriver(): ImagickDriver
    {
        return new ImagickDriver;
    }

    /**
     * Register a transformation handler for the given driver.
     *
     * @param  class-string<\Mariner\Contracts\Image\Transformation>  $transformation
     */
    public function transformUsing(string $driver, string $transformation, callable $callback): static
    {
        $this->transformationHandlers[$driver][$transformation] = $callback;

        if (isset($this->drivers[$driver])) {
            $this->applyTransformationHandlers($driver, $this->drivers[$driver]);
        }

        return $this;
    }

    /**
     * Apply registered transformation handlers to the given driver instance.
     */
    protected function applyTransformationHandlers(string $driver, Driver $instance): void
    {
        foreach ($this->transformationHandlers[$driver] ?? [] as $transformation => $callback) {
            $instance->transformUsing($transformation, $callback);
        }
    }

    /**
     * Get the default image driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('images.default', 'gd');
    }
}
