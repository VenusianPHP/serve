---
type: Module
title: Translation
description: Translator with file and array loaders and pluralization selector.
resource: src/Mariner/Translation/Translator.php
tags: [translation, i18n]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/Translation/TranslationServiceProvider.php
    title: TranslationServiceProvider
  - id: translator
    resource: src/Mariner/Translation/Translator.php
    title: Translator
  - id: loader
    resource: src/Mariner/Translation/FileLoader.php
    title: FileLoader
  - id: facade
    resource: src/Mariner/NutsAndBolts/Facades/Lang.php
    title: Lang
---

# Overview

Translator, FileLoader, ArrayLoader, MessageSelector, PotentiallyTranslatedString; bundled lang/ directory; contracts in src/Mariner/Contracts/Translation (HasLocalePreference, Loader, Translator).[^translator]

Provider binds `'translator'` and `'translation.loader'` via registerSingleton; deferred, provides both. Loader reads `__DIR__.'/lang'` and `$app['path.lang']` through `$app['files']`.[^provider] Facade Lang, accessor `'translator'`.[^facade]

Voyager imports: Voyager\Contracts\NutsAndBolts, Voyager\Filesystem, Voyager\NutsAndBolts (Collection, Concerns, DataObjects, ServiceProvider, Str).[^translator][^loader] Reads no config keys.

[^provider]: TranslationServiceProvider
[^translator]: Translator
[^loader]: FileLoader
[^facade]: Lang
