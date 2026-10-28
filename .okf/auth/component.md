---
type: Module
title: Auth
description: Guards, user providers, authorization gate and password reset brokers.
resource: src/Mariner/Auth/AuthManager.php
tags: [auth, guard, gate, passwords]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/Auth/AuthServiceProvider.php
    title: AuthServiceProvider
  - id: manager
    resource: src/Mariner/Auth/AuthManager.php
    title: AuthManager
  - id: pwprovider
    resource: src/Mariner/Auth/Passwords/PasswordResetServiceProvider.php
    title: PasswordResetServiceProvider
  - id: pwmanager
    resource: src/Mariner/Auth/Passwords/PasswordBrokerManager.php
    title: PasswordBrokerManager
  - id: creates
    resource: src/Mariner/Auth/CreatesUserProviders.php
    title: CreatesUserProviders
  - id: guard
    resource: src/Mariner/Auth/SessionGuard.php
    title: SessionGuard
  - id: f-auth
    resource: src/Mariner/NutsAndBolts/Facades/Auth.php
    title: Auth facade
  - id: f-gate
    resource: src/Mariner/NutsAndBolts/Facades/Gate.php
    title: Gate facade
  - id: f-password
    resource: src/Mariner/NutsAndBolts/Facades/Password.php
    title: Password facade
  - id: resetnotif
    resource: src/Mariner/Auth/Notifications/ResetPassword.php
    title: ResetPassword
---

# Overview

Authentication guards (session, token, request), database and Eloquent user providers, the `Gate` authorization layer, auth middleware, events, notifications and password reset brokers[^manager][^guard].

`AuthServiceProvider` registers singletons `'auth'` (AuthManager), `'auth.driver'` and `Mariner\Contracts\Auth\Access\Gate`; binds `Mariner\Contracts\Auth\Authenticatable` and `RequirePassword`[^provider]. `PasswordResetServiceProvider` (deferred) registers singleton `'auth.password'` and binds `'auth.password.broker'`[^pwprovider].

Facades: `Auth` → `'auth'`[^f-auth], `Gate` → `GateContract::class`[^f-gate], `Password` → `'auth.password'`[^f-password].

Voyager imports: `Voyager\NutsAndBolts\{ServiceProvider, Str, Collection, DataObjects\Arr, DataObjects\Carbon, Concerns\Macroable}`, `Voyager\Contracts\{Hashing\Hasher, NutsAndBolts\Arrayable, NutsAndBolts\DeferrableProvider, Signals\SignalDispatcher, Vessel\TheServiceContainer}`, `Voyager\Database\{ConnectionInterface, Instrument\Model, Instrument\Attributes\UsePolicy}`, `Voyager\Cache\Repository`, `Voyager\Console\Command`, `Voyager\Queue\SerializesModels`.

Config keys: `auth.guards.{name}`, `auth.defaults.guard`[^manager]; `auth.providers.{name}`, `auth.defaults.provider`[^creates]; `auth.passwords.{name}`, `auth.defaults.passwords`, `app.key`[^pwmanager]; `auth.password_timeout`[^provider]; `auth.passwords.*.expire`[^resetnotif].

# Requests

Guards live per scope (`GuardState`, scoped), so each request `php computer serve` runs in its own fiber builds its own guard over its own request: proven with interleaved basic-auth requests (`tests/Auth/AuthRequestIsolationTest.php`). Laravel also hands every cached guard each new request through `refresh('request')` rebinding; Vessel has none, so `AuthManager::guard()` gives the guard the current request, and the current `signals` dispatcher (Laravel's `rebinding('events')`), each time it is asked for. Without that, calls sharing one scope (a feature test's) read the first call's credentials.

# Async

Opt-in (`e9ffd07`). `DatabaseUserProvider::via(?pool)` offloads all five calls; `InstrumentUserProvider::via()` offloads all five: lookups as queries, and the two writes (remember token, rehash) through the model's own `saveAsync()` (framework `1af5116`), so events and timestamps run here and only the SQL runs in a worker. Password token repositories: `DatabaseTokenRepository::via()`, `CacheTokenRepository::async()`. Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

# Open items

- Per-request state bound with `registerSingleton`, so one instance is shared across fibers: 'auth' AuthServiceProvider.php:35.

[^provider]: AuthServiceProvider
[^manager]: AuthManager
[^pwprovider]: PasswordResetServiceProvider
[^pwmanager]: PasswordBrokerManager
[^creates]: CreatesUserProviders
[^guard]: SessionGuard
[^f-auth]: Auth facade
[^f-gate]: Gate facade
[^f-password]: Password facade
[^resetnotif]: ResetPassword
