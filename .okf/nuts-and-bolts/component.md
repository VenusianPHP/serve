---
type: Module
title: NutsAndBolts
description: Web support classes, testing fakes and Mariner's facades.
resource: src/Mariner/NutsAndBolts/Facades/Facade.php
tags: [support, facades, fakes, mariner]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: facade
    resource: src/Mariner/NutsAndBolts/Facades/Facade.php
    title: Facade
  - id: messagebag
    resource: src/Mariner/NutsAndBolts/MessageBag.php
    title: MessageBag
  - id: uri
    resource: src/Mariner/NutsAndBolts/Uri.php
    title: Uri
  - id: localizable
    resource: src/Mariner/NutsAndBolts/Traits/Localizable.php
    title: Localizable
  - id: loads
    resource: src/Mariner/NutsAndBolts/Concerns/LoadsWebResources.php
    title: LoadsWebResources
  - id: macros
    resource: src/Mariner/NutsAndBolts/StringMacros.php
    title: StringMacros
  - id: fake
    resource: src/Mariner/NutsAndBolts/Testing/Fake.php
    title: Fake
  - id: contracts
    resource: src/Mariner/Contracts/NutsAndBolts/MessageBag.php
    title: Contracts\NutsAndBolts\MessageBag
---

# Overview

Support classes: Benchmark, DateFactory, EncodedHtmlString, Js, MessageBag, ViewErrorBag, NamespacedItemResolver, NodePackageManager, ProcessUtils, Timebox, Uri, UriQueryString, ValidatedInput, StringMacros, RebindsCallbacksToSelf[^messagebag][^uri][^macros]. `StringMacros` adds the web methods of Str/Stringable absent from Venusian's copies[^macros]; `Concerns\LoadsWebResources` adds the web half of ServiceProvider[^loads]. Traits: Localizable, ParsesSqlServerConfigurationUrls, ReadsClassAttributes[^localizable]. `Testing\Fake` and `Testing\MatchesProperties` are the shared base of the web fakes, which live with their packages (`Mail\Testing\MailFake`, `PendingMailFake`, `Notifications\Testing\NotificationFake`); the framework's own fakes are Voyager's.[^fake] Contracts in `Mariner\Contracts\NutsAndBolts`: MessageBag, MessageProvider, Renderable, Responsable, ValidatedData[^contracts].

No service provider. Facades (`Mariner\NutsAndBolts\Facades`, base `Facade`[^facade]) and accessors:

- Auth: `auth`
- Blade: `blade.compiler`
- Cookie: `cookie`
- Date: `date`
- Gate: `Mariner\Contracts\Auth\Access\Gate::class`
- Image: `image`
- Lang: `translator`
- Mail: `mail.manager`
- Notification: `Mariner\Notifications\ChannelManager::class`
- Password: `auth.password`
- Redirect: `redirect`
- Request: `request`
- Response: `Mariner\Contracts\Routing\ResponseFactory::class`
- Route: `router`
- Session: `session`
- URL: `url`
- Validator: `validator`
- View: `view`
- Vite: `Mariner\View\Vite::class`

`Facade::defaultAliases()` also maps Arr, Benchmark, Js, Number, Str, Uri[^facade].

Voyager imports: `Voyager\NutsAndBolts`, `Voyager\Vessel`, `Voyager\Database`, `Voyager\Contracts\NutsAndBolts`. Requires `league/uri` for `Uri`.[^facade] No `config()` reads.

# Open items

- Laravel container methods `Voyager\Vessel\ControlPanel` lacks: `routesAreCached()` Concerns/LoadsWebResources.php:21.
- Laravel wording: Concerns/LoadsWebResources.php:9, Facades/Request.php:43, StringMacros.php:9.

[^facade]: Facade
[^messagebag]: MessageBag
[^uri]: Uri
[^localizable]: Localizable
[^loads]: LoadsWebResources
[^macros]: StringMacros
[^fake]: Fake
[^contracts]: Contracts\NutsAndBolts\MessageBag
