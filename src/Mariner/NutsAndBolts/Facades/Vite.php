<?php

namespace Mariner\NutsAndBolts\Facades;

/**
 * @method static array preloadedAssets()
 * @method static string|null cspNonce()
 * @method static string useCspNonce(string|null $nonce = null)
 * @method static \Mariner\View\Vite useIntegrityKey(string|false $key)
 * @method static \Mariner\View\Vite withEntryPoints(array $entryPoints)
 * @method static \Mariner\View\Vite mergeEntryPoints(array $entryPoints)
 * @method static \Mariner\View\Vite useManifestFilename(string $filename)
 * @method static \Mariner\View\Vite createAssetPathsUsing(callable|null $resolver)
 * @method static string hotFile()
 * @method static \Mariner\View\Vite useHotFile(string $path)
 * @method static \Mariner\View\Vite useBuildDirectory(string $path)
 * @method static \Mariner\View\Vite useScriptTagAttributes(callable|array $attributes)
 * @method static \Mariner\View\Vite useStyleTagAttributes(callable|array $attributes)
 * @method static \Mariner\View\Vite usePreloadTagAttributes(callable|array|false $attributes)
 * @method static \Mariner\View\Vite prefetch(int|null $concurrency = null, string $event = 'load')
 * @method static \Mariner\View\Vite useWaterfallPrefetching(int|null $concurrency = null)
 * @method static \Mariner\View\Vite useAggressivePrefetching()
 * @method static \Mariner\View\Vite usePrefetchStrategy(string|null $strategy, array $config = [])
 * @method static \Voyager\NutsAndBolts\HtmlString|void reactRefresh()
 * @method static string|null devServerUrl()
 * @method static string asset(string $asset, string|null $buildDirectory = null)
 * @method static string content(string $asset, string|null $buildDirectory = null)
 * @method static string|null manifestHash(string|null $buildDirectory = null)
 * @method static \Voyager\NutsAndBolts\HtmlString fonts(array|string|null $aliases = null)
 * @method static \Mariner\View\Vite useFontsManifestFilename(string $filename)
 * @method static bool isRunningHot()
 * @method static string toHtml()
 * @method static void flush()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 *
 * @see \Mariner\View\Vite
 */
class Vite extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Mariner\View\Vite::class;
    }
}
