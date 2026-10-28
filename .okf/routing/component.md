---
type: Module
title: Routing
description: Router, routes, URL generation, redirects, controller dispatch and routing middleware.
resource: src/Mariner/Routing/Router.php
tags: [routing, url, controllers]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: router
    resource: src/Mariner/Routing/Router.php
    title: Router
  - id: provider
    resource: src/Mariner/Routing/RoutingServiceProvider.php
    title: RoutingServiceProvider
  - id: url-contract
    resource: src/Mariner/Contracts/Routing/UrlGenerator.php
    title: UrlGenerator
---

# Overview

Router, Route, RouteCollection and CompiledRouteCollection, UrlGenerator, Redirector, ResponseFactory, controller and callable dispatchers, resource registrars, attributes and middleware.[^router] Contracts in Contracts/Routing: BindingRegistrar, Registrar, ResponseFactory, UrlGenerator, UrlRoutable.[^url-contract]

RoutingServiceProvider binds 'router', 'url', 'redirect' (registerSingleton), 'routes' (registerInstance), ServerRequestInterface::class and ResponseInterface::class (bind), ResponseFactory, CallableDispatcher and ControllerDispatcher contracts (registerSingleton).[^provider] It reads 'webapp.asset_url', 'app.key', 'app.previous_keys' from the config repository.[^provider]

Voyager namespaces imported: Voyager\Cache\{RateLimiter,RateLimiting}, Voyager\Console\{Concerns,GeneratorCommand}, Voyager\Contracts\{Debug,NutsAndBolts,Redis,Signals,Vessel}, Voyager\Database\Instrument, Voyager\NutsAndBolts\{Collection,Concerns,DataObjects,Reflector,ServiceProvider,Str}, Voyager\Pipeline\Pipeline, Voyager\Redis\Limiters, Voyager\Vessel\ControlPanel.

# Instrument models in URLs

Voyager's Instrument `Model` keeps `getRouteKey()`/`getRouteKeyName()` but not the `UrlRoutable` contract. `InstrumentRouteBinding::isRoutable()` (a `UrlRoutable` or a plain Instrument model) is what URL generation checks, so a model given to `route()`, `to()`, a `{param:field}` binding or a `Uri` query becomes its route key, as Laravel's Eloquent models do.

# Precognition

`HandlePrecognitiveRequests` (alias `precognitive`) marks a request that sends `Precognition: true`; the route then resolves its parameters (so form requests validate, with `Precognition-Validate-Only` narrowing the rules) and answers 204 `Precognition-Success` without running its action, through `PrecognitionCallableDispatcher`/`PrecognitionControllerDispatcher`. Laravel swaps the dispatcher bindings on the container for the request and restores them after; under a server running requests side by side that would reach the others, so here `Route` picks the Precognition dispatchers (or those the middleware's `dispatchUsing()` chose for the request) from the current, per-fiber request, whatever dispatchers the app has bound. Responses vary on `Precognition`. `$request->validate()` applies the precognitive rule filter and after-hook, as Laravel's macro does.

# Async

`routing.throttle_async` (default off): `ThrottleRequests` routes its limiter calls through the framework's `RateLimiter::async()` (`62e03e6`), waited on in place; the limiter's cache store must have async operations, or the request fails loudly. `ThrottleRequestsWithRedis` keeps its own Redis limiter and is not affected.

`routing.binding_via` (`27743bf`, default null = in place): set to `auto`, `process` or `thread` and `InstrumentRouteBinding` runs the binding query on that pool, waited on in place. Models that resolve themselves (`UrlRoutable`, `BindsToRoutes`) are untouched; a model's `resolveRouteBindingQuery()` is honoured; a pool that is off fails loudly. Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

# Open items

- Laravel wording: RoutingServiceProvider.php:110, RoutingServiceProvider.php:111, RoutingServiceProvider.php:113, RoutingServiceProvider.php:118.

[^router]: Router
[^provider]: RoutingServiceProvider
[^url-contract]: UrlGenerator
