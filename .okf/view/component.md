---
type: Module
title: View
description: View factory, finder, engines and the Blade compiler.
resource: src/Mariner/View/Factory.php
tags: [view, blade, templating]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-28T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/View/ViewServiceProvider.php
    title: ViewServiceProvider
  - id: factory
    resource: src/Mariner/View/Factory.php
    title: Factory
  - id: component
    resource: src/Mariner/View/Component.php
    title: Component
  - id: facade-view
    resource: src/Mariner/NutsAndBolts/Facades/View.php
    title: View
  - id: facade-blade
    resource: src/Mariner/NutsAndBolts/Facades/Blade.php
    title: Blade
  - id: vite
    resource: src/Mariner/View/Vite.php
    title: Vite
  - id: view-helpers
    resource: src/Mariner/View/helpers.php
    title: View helpers
  - id: fiber-buffers
    resource: tests/View/FiberOutputBuffersTest.php
    title: FiberOutputBuffers tests
---

# Overview

View factory, FileViewFinder, engine resolver (file, php, blade engines), BladeCompiler and Blade components; contracts in src/Mariner/Contracts/View (Engine, Factory, View, ViewCompilationException).[^factory][^provider]

Provider binds `'view'`, `'blade.compiler'`, `'view.engine.resolver'` via registerSingleton and `'view.finder'` via bind.[^provider] Facades: View, accessor `'view'`; Blade, accessor `'blade.compiler'`.[^facade-view][^facade-blade]

Voyager imports: Voyager\Contracts\{Core,NutsAndBolts,Signals,Vessel}, Voyager\Database (RecordNotFoundException, RecordsNotFoundException), Voyager\Filesystem, Voyager\NutsAndBolts (Collection, Concerns, Contracts, DataObjects, HtmlString, LazyCollection, Reflector, ServiceProvider, Str), Voyager\Vessel\ControlPanel.[^factory]

Config keys: `view.paths`, `view.compiled`, `view.relative_hash`, `view.cache`, `view.compiled_extension`, `view.check_cache_timestamps` (ViewServiceProvider.php:85-103); `view.compiled` also Component.php:200.[^provider][^component]

`Vite` (with `ViteFonts`, `ViteException`, `ViteManifestNotFoundException`) builds asset tags from the Vite manifest; the `Vite` facade resolves `Mariner\View\Vite::class`. View's `helpers.php` defines `view()` and `method_field()`, each behind `function_exists`.[^vite][^view-helpers]

`FiberOutputBuffers` gives each loop fiber its own output buffers: a suspending fiber's buffers come off PHP's one stack and are parked with it, and go back on when it resumes (by resumption, a throw or a cancel). A buffer opened through `start()` keeps its callback across suspends. A fiber torn down while suspended (its loop or the process gone) runs its `finally` with its buffers still parked, so `end()` leaves the stack alone rather than pop a buffer that isn't its own.[^fiber-buffers]

# Console

`view:cache` clears, then compiles every Blade template under the finder's paths and namespace hints (packages' views too, `vendor` excluded); `view:clear` empties `view.compiled` (files and directories) and forgets the engine's compiled-or-not-expired memo, failing when `view.compiled` is unset. Both from Laravel v13.37.0's Foundation console, registered by `ViewServiceProvider`.

# Open items

- Laravel wording: Compilers/ComponentTagCompiler.php:19, Vite.php:734, Vite.php:751, Vite.php:764.

[^provider]: ViewServiceProvider
[^factory]: Factory
[^component]: Component
[^facade-view]: View
[^facade-blade]: Blade
[^vite]: Vite
[^view-helpers]: View helpers
[^fiber-buffers]: FiberOutputBuffers tests
