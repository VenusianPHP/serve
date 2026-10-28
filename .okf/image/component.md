---
type: Module
title: Image
description: Image manager, driver-backed image pipeline, transformations and output options.
resource: src/Mariner/Image/ImageManager.php
tags: [image, manager]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: manager
    resource: src/Mariner/Image/ImageManager.php
    title: ImageManager
  - id: image
    resource: src/Mariner/Image/Image.php
    title: Image
  - id: provider
    resource: src/Mariner/Image/ImageServiceProvider.php
    title: ImageServiceProvider
  - id: driver
    resource: src/Mariner/Contracts/Image/Driver.php
    title: Driver
---

# Overview

ImageManager (extends Voyager\NutsAndBolts\Manager), Image, ImagePipeline, ImageOutputOptions, ImageException and Transformations.[^manager][^image] Contracts: Contracts/Image/Driver and Transformation.[^driver]

ImageServiceProvider is deferred and binds 'image' via scoped() (one instance per fiber); provides() returns ['image'].[^provider] Image resolves 'image' and Voyager\Contracts\Filesystem\Factory through ControlPanel::getInstance()->make().[^image]

Config read: 'images.default' (default 'gd').[^manager] Fetches remote images through Voyager\Http\Client\Factory.[^manager]

Voyager namespaces imported: Voyager\Contracts\{Filesystem,NutsAndBolts}, Voyager\Filesystem\Filesystem, Voyager\Http\Client, Voyager\NutsAndBolts\{Concerns,Manager,ServiceProvider,Str}, Voyager\Vessel\ControlPanel.

# Async

`ImageManager::via(?pool)` returns a copy whose images load (`fromPath`, `fromStorage`, `fromUpload` through the offloaded filesystem; `fromUrl` on the event loop), run their pipeline (`ProcessImage` builds the named driver on a worker) and store (`disk->via()`), each waited on in place; `Image::via()` does the same for one image. A worker failure surfaces as `ImageException`. Refused clearly: drivers registered with `extend()` and drivers with runtime transformation handlers, closures a worker never receives. `intervention/image` is require-dev so the real GD path is tested. Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

[^manager]: ImageManager
[^image]: Image
[^provider]: ImageServiceProvider
[^driver]: Driver
