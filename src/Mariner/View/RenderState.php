<?php

namespace Mariner\View;

/**
 * Everything the view factory tracks while it renders a request's views.
 *
 * Registered with scoped(), so concurrent request fibers each render with their own sections,
 * stacks, slots, loops and fragments, even when one suspends halfway through a layout (a view
 * composer awaiting a query, say). The factory singleton keeps engines, finder, extensions,
 * composers and the data shared at boot.
 */
final class RenderState
{
    public int $renderCount = 0;

    public array $renderedOnce = [];

    /** Data shared while handling this request (ShareErrorsFromSession's $errors, middleware shares). */
    public array $shared = [];

    public array $componentStack = [];

    public array $componentData = [];

    public array $currentComponentData = [];

    public array $slots = [];

    public array $slotStack = [];

    public array $fragments = [];

    public array $fragmentStack = [];

    public array $sections = [];

    public array $sectionStack = [];

    public array $loopsStack = [];

    public array $pushes = [];

    public array $prepends = [];

    public array $pushStack = [];

    public array $translationReplacements = [];
}
