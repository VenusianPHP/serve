<?php

namespace Mariner\NutsAndBolts\Facades;

/**
 * @method static \Mariner\Image\Image fromBytes(string $contents)
 * @method static \Mariner\Image\Image fromStream(resource $stream)
 * @method static \Mariner\Image\Image fromBase64(string $base64)
 * @method static \Mariner\Image\Image fromPath(string $path)
 * @method static \Mariner\Image\Image fromStorage(string $path, \BackedEnum|string|null $disk = null)
 * @method static \Mariner\Image\Image fromUpload(\Voyager\Filesystem\UploadedFile $file)
 * @method static \Mariner\Image\Image fromUrl(string $url)
 * @method static \Mariner\Image\ImageManager transformUsing(string $driver, string $transformation, callable $callback)
 * @method static string getDefaultDriver()
 * @method static mixed driver(\UnitEnum|string|null $driver = null)
 * @method static \Mariner\Image\ImageManager extend(string $driver, \Closure $callback)
 * @method static array getDrivers()
 * @method static \Voyager\Contracts\Vessel\TheServiceContainer getContainer()
 * @method static \Mariner\Image\ImageManager setContainer(\Voyager\Contracts\Vessel\TheServiceContainer $container)
 * @method static \Mariner\Image\ImageManager forgetDrivers()
 *
 * @see \Mariner\Image\ImageManager
 */
class Image extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'image';
    }
}
