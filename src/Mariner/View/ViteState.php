<?php

namespace Mariner\View;

/**
 * Vite's per-response state: the CSP nonce, the assets preloaded so far, and the entry points
 * set for the next render.
 *
 * Registered with scoped(), so each response gets its own nonce and preload headers, while the
 * Vite singleton keeps its configuration (build directory, hot file, resolvers, prefetching).
 */
final class ViteState
{
    public ?string $nonce = null;

    public array $preloadedAssets = [];

    public array $entryPoints = [];
}
