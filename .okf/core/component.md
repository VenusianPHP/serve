---
type: Module
title: Core
description: Mariner's Foundation layer, shipped only in serve and never split; the root provider, the HTTP kernel, the exception renderer and install:web.
resource: src/Mariner/Core
tags: [core, foundation, provider, kernel, exceptions, install]
status: draft
generated: { by: claude-code/claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/Core/Providers/MarinerServiceProvider.php
    title: MarinerServiceProvider
  - id: manifest
    resource: composer.json
    title: Root manifest
  - id: move
    resource: git:cd3ad91
    title: Give each Mariner package its own helpers; Core ships only in serve
  - id: renderer
    resource: git:0968482
    title: Render web exceptions in Mariner, reporting each once
  - id: kernel
    resource: git:1c21ee0
    title: Add Mariner's HTTP kernel
  - id: install
    resource: git:9dd438f
    title: Add install:web and load the app's route files
  - id: formrequest
    resource: git:922b2a7
    title: Add form requests
---


# Overview

`Mariner\Core` is Mariner's Foundation layer: what composes several components, as Laravel's `Illuminate\Foundation` does. It ships only in `venusian/serve`, never as a `venusian-mariner` package, and `.github/check-core-isolation.php` fails CI if a split package (source, stub or manifest) names it. The root manifest's `extra.venusian.providers` names `Mariner\Core\Providers\MarinerServiceProvider`, so a Venusian app discovers it.[^manifest]

`MarinerServiceProvider` aggregates the Http, Cookie, Session, Routing, Auth, PasswordReset, Translation, Validation, FormRequest, View, Mail, Notification and Image providers, and binds the kernel, the exception renderer (aliased to `Mariner\Contracts\Http\ExceptionRenderer`) and `install:web` as singletons. `register()` merges serve's `config/` files (`auth`, `cors`, `images`, `mail`, `routing`, `serve`, `services`, `session`, `view`, `webapp`), binds `path.public`, `path.resources` and `path.lang` under the base path unless the app already has, sets the facade root (`Facade::setFacadeApplication`), and registers `StringMacros`. `boot()` publishes every config file under the tag `mariner-config`. The web keys (`url`, `asset_url`, `locale`, `fallback_locale`, `maintenance.*`) live in serve's own `webapp.php` and are read as `webapp.*`; nothing merges into the app's `app.php`, which stays the framework's (`name`, `key`, `previous_keys`, `timezone`, …).[^provider]

The old Core component's classes moved out in `cd3ad91`: Vite to [View](/view/component.md), Precognition to [Validation](/validation/component.md), the helpers to each owning package.[^move]

# HTTP kernel

`Mariner\Core\Http\Kernel` handles a web request as Laravel v13.37.0's Foundation kernel does, for the front controller and `php computer serve` alike.[^kernel]

* `handle()` bootstraps the app once, registers the request in the current scope (`registerInstance('request')`, which Vessel keeps per fiber), hands it to a URL generator the scope already built, then runs it through the global middleware into `Router::dispatch()`. Any exception goes to the exception renderer.
* Global middleware: `InvokeDeferredCallbacks`, `TrustProxies`, `HandleCors`, `ValidatePostSize`, `TrimStrings`, `ConvertEmptyStringsToNull`. Group `web`: cookies (encrypt, queue), `StartSession`, `ShareErrorsFromSession`, `PreventRequestForgery` (CSRF), `SubstituteBindings`. Group `api`: `SubstituteBindings`. Laravel's default aliases (`auth`, `can`, `guest`, `signed`, `throttle`, `verified`, ...) and middleware priority. `pushMiddleware()`, `appendMiddlewareToGroup()` and the priority methods change them.
* `terminate()` runs after the response is sent: it dispatches `Mariner\Http\Events\RequestHandled`, calls `terminate()` on terminable middleware (so `defer()` callbacks run for web requests, skipped on a 4xx/5xx unless `->always()`), the app's terminating callbacks, then `whenRequestLifecycleIsLongerThan()` handlers.
* One kernel serves every request, so the only request state on it is each request's start time, in a `WeakMap` keyed by the request.

# Exception rendering

Voyager's exception handler reports and never renders: rendering a web response is Mariner's. `Mariner\Core\Http\ExceptionRenderer::handle()` reports through Voyager's handler exactly once per exception (a `WeakMap` guard), then renders. A render that throws is reported too and answered with a plain 500; a reporter that throws is written to `error_log()` and the response still goes out.[^renderer]

* Mapping, per Laravel's Foundation handler: an exception's own `render()` or `Responsable`; renderable callbacks (`renderable()`); 404 for `ModelNotFoundException`, `RecordNotFoundException`, `RecordsNotFoundException`, `BackedEnumCaseNotFoundException`; 403 for `AuthorizationException` (or its status) and `OriginMismatchException`; 419 for `TokenMismatchException`; 400 for Symfony's `RequestExceptionInterface`; `HttpResponseException`'s response; 401 JSON or a redirect for `AuthenticationException`; 422 JSON or a redirect back with errors and input (minus `dontFlash()`) for `ValidationException`.
* JSON when `$request->expectsJson()` or `shouldRenderJsonWhen()` says so. HTML: the app's `errors.{status}` or `errors.{n}xx` view, else Symfony's `HtmlErrorRenderer`. With `app.debug` off a server error shows no message.
* `Mariner\Routing\Pipeline` renders exceptions thrown by route middleware through the `ExceptionRenderer` contract, so Routing never names Core.

# install:web and route files

`php computer install:web` publishes `routes/web.php` (`/` renders the welcome page; `/up` answers `OK`, so health works under FPM too, though `serve` answers it first), `routes/api.php`, `public/index.php` (the front controller for FPM and Herd) and `resources/views/welcome.blade.php` (self-contained: the serve and framework versions, PHP version and SAPI, and doc links) from `src/Mariner/Core/Console/stubs`. It writes only missing files and reports the rest as skipped; `--force` overwrites those four and nothing else; a file it cannot write is named and the command exits 1. `RoutingServiceProvider` loads the files `config/routing.php` names (`web`, `api` under `api_prefix`) when the routes are not cached; a key set to null loads nothing.[^install]

`FormRequestServiceProvider` (in Validation) fills each `Mariner\Validation\FormRequest` from the current request and validates it when the container resolves it.[^formrequest]

# Open items

* The rest of Laravel's Foundation that a web app touches is built: `Mariner\Auth\User` with `Access\Authorizable` (`54ea030`), `Access\AuthorizesRequests` (`f4c2b63`), `Mariner\Validation\ValidatesRequests` (`843ccf0`), `Mariner\Core\Auth\EmailVerificationRequest` (`d9b126d`, in Core because it is a form request about users), and the `$policies` base `Mariner\Auth\Support\AuthServiceProvider` (`4c75ee3`).
* Backlog: an HTML `dd()` dumper; the `/up` health route, with `php computer serve`; a static `Event::dispatch()` convenience (framework); `app()->setLocale()` is absent (the translator owns the locale); a `Terminating` event.
* Test harness `Mariner\Core\Testing` (root-only: it drives Core's `Kernel` and swaps Core's `ExceptionRenderer`), from Laravel v13.37.0 `Foundation\Testing`: `TestCase` (abstract `createApplication()`; each used trait's `setUp{Trait}()`/`tearDown{Trait}()` runs around every test) with `MakesHttpRequests` (kernel `handle()` → `TestResponse`, then `terminate()`; no fiber per request, so session, auth and scoped instances persist across calls in one test), `InteractsWithAuthentication`, `InteractsWithSession`, `InteractsWithViews`, `InteractsWithExceptionHandling` (`withoutExceptionHandling()` binds a renderer that rethrows), `InteractsWithContainer` (no Mix), the framework's `Voyager\Console\Testing\InteractsWithConsole` (`artisan()`, `PendingCommand`), and `WithoutMiddleware`. Pest binds one TestCase per directory, so a suite opts in with `uses(Mariner\Core\Testing\TestCase::class)->in(...)` or test classes. Database helpers live in the framework (`Voyager\Database\Testing`) and plug into this `TestCase` through its hooks: `use RefreshDatabase, InteractsWithDatabase;` in a test class (or `uses(TestCase::class, RefreshDatabase::class)->in(...)`), with `createApplication()` pointing the default connection at a database file and the migrator at the app's migrations; `tests/Harness/RefreshDatabaseHarnessTest.php` is the worked example. An app's suite sets `APP_ENV=testing`, as Laravel's `phpunit.xml` does.
* Laravel v13.37.0's `FoundationServiceProvider`, swept: `FormRequestServiceProvider`, `Vite` and the `Uri` URL resolver were already in Mariner; the HTTP client factory and the defer handler are in the framework. Ported: the `validate`/`validateWithBag` request macros (Validation's `FormRequestServiceProvider`), the `hasValidSignature*` macros (Routing's provider; `ValidateSignature` needs them), and exception tracking (`LoggedExceptionCollection` fed from `MessageLogged` under unit tests, here). Backlogged: the console schedule, the HTML dumper, the debug exception page, parallel testing.

[^provider]: MarinerServiceProvider
[^manifest]: Root manifest
[^move]: Give each Mariner package its own helpers; Core ships only in serve
[^renderer]: Render web exceptions in Mariner, reporting each once
[^kernel]: Add Mariner's HTTP kernel
[^install]: Add install:web and load the app's route files
[^formrequest]: Add form requests
