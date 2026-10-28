---
type: Module
title: Http
description: Request, response, uploaded-file, JSON resource and HTTP middleware classes on Symfony HttpFoundation.
resource: src/Mariner/Http/Request.php
tags: [http, request, response, middleware]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: request
    resource: src/Mariner/Http/Request.php
    title: Request
  - id: uploaded-file
    resource: src/Mariner/Http/UploadedFile.php
    title: UploadedFile
  - id: json-resource
    resource: src/Mariner/Http/Resources/Json/JsonResource.php
    title: JsonResource
  - id: cors
    resource: src/Mariner/Http/Middleware/HandleCors.php
    title: HandleCors
  - id: trust-hosts
    resource: src/Mariner/Http/Middleware/TrustHosts.php
    title: TrustHosts
  - id: trust-proxies
    resource: src/Mariner/Http/Middleware/TrustProxies.php
    title: TrustProxies
---

# Overview

Request, Response, JsonResponse, RedirectResponse, StreamedEvent, UploadedFile and File; API resources under Resources/Json and Resources/JsonApi; middleware under Middleware.[^request] No service provider and no contracts directory. No HTTP client: outbound HTTP lives in Voyager\Http.

Resolves services through Voyager\Vessel\ControlPanel::getInstance()->make(), keys 'request' and Voyager\Contracts\Filesystem\Factory.[^uploaded-file][^json-resource]

Voyager namespaces imported: Voyager\Contracts\{Core,Filesystem,NutsAndBolts,Vessel}, Voyager\Database\Instrument, Voyager\NutsAndBolts\{Collection,Concerns,DataObjects,Fluent,LazyCollection,Str}, Voyager\Pagination, Voyager\Vessel\ControlPanel.

Config read: config('trustedproxy.proxies')[^trust-proxies], 'cors' and 'cors.paths'[^cors], 'webapp.url'[^trust-hosts].

# Resources from models

Laravel gives every Eloquent model `toResource()` and every collection and paginator `toResourceCollection()`. Voyager's Instrument `Model` stays HTTP-agnostic, so in Mariner it is opt-in per model: `use Mariner\Http\Resources\TransformsToResource;`. `#[UseResource]`/`#[UseResourceCollection]` (in `Mariner\Http\Resources\Attributes`) and the `App\Http\Resources\{Model}Resource`/`{Model}` guess work as in Laravel v13.37.0 (`ResourceGuesser`). Collections and both paginators get `toResourceCollection()` from macros `HttpServiceProvider` registers (the framework's paginators are macroable since framework `191935a`); a paginator's resource collection answers with its links and meta. Resource collections over Instrument models work since the framework dropped `mapInto()`'s native `static` return type (framework `5a45b2c`).

# Precognition

Deviation from Laravel, for requests running side by side. Laravel's `HandlePrecognitiveRequests` binds the Precognition dispatchers on the container for the request and puts the old bindings back after; apps that want the real action to run override `prepareForPrecognition()` and `app()->bind()` the real dispatchers. Container bindings are global in Vessel, so under `php computer serve` either swap would reach every other request the server is running. Mariner keeps it all on the request: the middleware marks it `precognitive`, the route runs the Precognition dispatchers for a marked request, and an app chooses other dispatchers for its own precognitive requests from `prepareForPrecognition()` with `$this->dispatchUsing($request, callable: fn ($app) => ..., controller: fn ($app) => ...)`. A hook that rebinds `CallableDispatcher`/`ControllerDispatcher` on the container (Laravel's style) is undone and refused with a `LogicException` naming `dispatchUsing()`. Ported Laravel code changes its `app()->bind()` lines to one `dispatchUsing()` call. See [Routing](../routing/component.md).

# Maintenance mode

Laravel v13.37.0's maintenance mode, all in Mariner (Angel's ruling). `Mariner\Contracts\Http\MaintenanceMode` (`activate`, `deactivate`, `active`, `data`) is bound per use to `MaintenanceModeManager`'s driver (`webapp.maintenance.driver`: `file` = `storage/framework/down`, read on every check with `clearstatcache($path)` first; `cache` = key `venusian:down` in `webapp.maintenance.store` or the default store, for several machines; `array` for tests). `PreventRequestsDuringMaintenance` is global, after `HandleCors` (Laravel's slot): excluded paths (`$except`, static `except()`) pass; the secret path redirects to the intended URL with the `venusian_maintenance` bypass cookie (base64 JSON of `expires_at` + HMAC-SHA256 under the secret, 12 hours, `hash_equals`); a valid cookie passes; otherwise a redirect or the prerendered template (not for JSON), else an `HttpException` with the payload's status and `Retry-After`/`Refresh`.

`php computer down` (Core, with `--redirect`, `--render`, `--retry`, `--refresh`, `--secret`, `--with-secret`, `--status`) writes the payload, renders `--render`'s view once into it, writes `storage/framework/maintenance.php` for the front controller's pre-boot check (the `install:web` `index.php` includes it; `php computer serve` does not need it) and fires `MaintenanceModeEnabled`; `up` removes both and fires `MaintenanceModeDisabled`.

Known gap: queue workers keep working while the app is down. The framework's `Worker` takes an `$isDownForMaintenance` callback, but `QueueServiceProvider` passes `fn () => false`; making it ask Mariner's contract is a candidate framework change for Angel.

# Open items

- Laravel wording: Concerns/InteractsWithInput.php:194, Request.php:432, Request.php:496.

[^request]: Request
[^uploaded-file]: UploadedFile
[^json-resource]: JsonResource
[^cors]: HandleCors
[^trust-hosts]: TrustHosts
[^trust-proxies]: TrustProxies
