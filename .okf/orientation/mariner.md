---
type: Orientation
title: Mariner
description: Laravel's web components under the Mariner namespace, built on individual venusian-voyager components; the framework never knows Mariner.
resource: src/Mariner
tags: [mariner, serve, voyager, dependency-direction]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: baseline
    resource: git:f15e03d
    title: "Baseline: Mariner import before cleanup"
  - id: dedupe
    resource: git:e70fd7d
    title: Remove components Venusian already provides
  - id: rename
    resource: git:5226d03
    title: Move Mariner onto its own namespace and Venusian's components
  - id: contracts
    resource: git:be72add
    title: Make every Mariner class load against Venusian's contracts
  - id: upstream
    resource: https://github.com/laravel/framework/tree/v13.37.0
    title: laravel/framework v13.37.0
---

# Overview

`venusian/serve` carries Laravel 13's web components (auth, cookie, http, routing, session, view, validation, translation, mail, notifications, image, json-schema, testing) as namespace `Mariner\`, source under `src/Mariner/<Component>`. Interfaces live in `src/Mariner/Contracts/<Component>`. Imported from Laravel, then cut down and re-pointed at Venusian.[^baseline][^rename]

Anything Venusian's framework already ships is not duplicated: the HTTP client, the NutsAndBolts classes, traits and helpers, and `BatchFake` were removed, and Mariner uses the `Voyager\` versions. Web-only pieces of the removed copies stayed as `NutsAndBolts\Concerns\LoadsWebResources` (`loadRoutesFrom`, `loadViewsFrom`, `loadViewComponentsAs`) and `StringMacros` (`Str::trans`, `Stringable::toUri`).[^dedupe]

# Upstream version

The import is laravel/framework **v13.37.0**. For every component, each file under `src/Mariner/<Component>` at the baseline `f15e03d` (still namespaced `Illuminate\`) has the same git blob hash as `src/Illuminate/<Component>` at v13.37.0; NutsAndBolts maps to `Support`. v13.37.0 is the only tag from v13.32.0 to v13.39.0 that matches every file in every component; v13.36.0 and v13.38.0 come closest. Test ports take their upstream tests from v13.37.0's `tests/<Component>`.[^baseline][^upstream]

| Component | Files | Component | Files |
|---|---|---|---|
| Auth | 52 | Routing | 79 |
| Cookie | 7 | Session | 19 |
| Http | 71 | Testing | 32 |
| Image | 23 | Translation | 13 |
| JsonSchema | 15 | Validation | 84 |
| Mail | 43 | View | 55 |
| Notifications | 29 | NutsAndBolts (Support) | 119 |

# Dependency direction

Serve depends on the framework; never the reverse. Mariner imports individual `Voyager\` components (by `use` count: NutsAndBolts, Contracts, Database, Vessel, Queue, Bus, Filesystem, Console, Pagination, Cache, Http, Config, Signals, Redis, Pipeline, Log, Core, Broadcasting). Nothing under `venusian/framework`'s `src/` names Mariner or serve. A serve need in the framework is a generic framework feature, described without web terms.[^rename][^contracts]

# Open items

* Laravel container calls `Voyager\Vessel\ControlPanel` lacks remain in Auth, Routing, Translation, View and NutsAndBolts; per-request state in Auth, Cookie, Routing and Session is bound with `registerSingleton`, not `scoped()`. Each component concept lists them with file:line.

[^baseline]: Baseline: Mariner import before cleanup
[^dedupe]: Remove components Venusian already provides
[^upstream]: laravel/framework v13.37.0
[^rename]: Move Mariner onto its own namespace and Venusian's components
[^contracts]: Make every Mariner class load against Venusian's contracts
