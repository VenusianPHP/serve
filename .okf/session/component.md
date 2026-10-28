---
type: Module
title: Session
description: Session manager, store, handlers and session middleware.
resource: src/Mariner/Session/SessionManager.php
tags: [session, http, middleware]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: manager
    resource: src/Mariner/Session/SessionManager.php
    title: SessionManager
  - id: provider
    resource: src/Mariner/Session/SessionServiceProvider.php
    title: SessionServiceProvider
  - id: store
    resource: src/Mariner/Session/Store.php
    title: Store
  - id: start
    resource: src/Mariner/Session/Middleware/StartSession.php
    title: StartSession
  - id: f-session
    resource: src/Mariner/NutsAndBolts/Facades/Session.php
    title: Session facade
---

# Overview

`SessionManager` (extends `Voyager\NutsAndBolts\Manager`) builds session drivers: array, cache, cookie, database, file, null, with optional `EncryptedStore`[^manager]. `Store` is the session store[^store]; `StartSession` and `AuthenticateSession` are the middleware[^start].

`SessionServiceProvider` registers singletons `StartSession::class`, `'session'` and `'session.store'`[^provider]. Facade `Session` → `'session'`[^f-session].

Voyager imports: `Voyager\NutsAndBolts\{ServiceProvider, Manager, Str, Collection, DataObjects\Arr, DataObjects\Carbon, Concerns\Macroable, Concerns\InteractsWithTime}`, `Voyager\Contracts\{Cache\Factory, Cache\Repository, Encryption\Encrypter, Encryption\DecryptException, Vessel\TheServiceContainer}`, `Voyager\Database\{ConnectionInterface, QueryException, Migrations\Migration, Schema\Blueprint}`, `Voyager\Filesystem\Filesystem`, `Voyager\Console\MigrationGeneratorCommand`.

Config keys (`session.*`): `driver`, `lifetime`, `expire_on_close`, `files`, `table`, `connection`, `prefix`, `store`, `encrypt`, `cookie`, `serialization`, `block`, `block_store`, `block_lock_seconds`, `block_wait_seconds`[^manager].

# Async

Opt-in, never sniffed (`ef9a128`, in-memory case `3e02eb8`). `Store::async(?pool)` answers start/save with Promises. File and database handlers have `via(?pool)` (database writes are one offloaded upsert; file gc runs as a gig), the cache handler has `async()`, and array/cookie handlers answer settled promises. `StartSession` uses it when `session.async` is on (default off; pool `session.async_pool`). Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

# Open items

- Per-request state bound with `registerSingleton`, so one instance is shared across fibers: StartSession::class SessionServiceProvider.php:22; 'session' SessionServiceProvider.php:36; 'session.store' SessionServiceProvider.php:48.
- Laravel wording: Middleware/StartSession.php:112, SymfonySessionDecorator.php:14.

[^manager]: SessionManager
[^provider]: SessionServiceProvider
[^store]: Store
[^start]: StartSession
[^f-session]: Session facade
