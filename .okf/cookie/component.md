---
type: Module
title: Cookie
description: Cookie jar with queued cookies and encrypt/queue middleware.
resource: src/Mariner/Cookie/CookieJar.php
tags: [cookie, http, middleware]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: jar
    resource: src/Mariner/Cookie/CookieJar.php
    title: CookieJar
  - id: provider
    resource: src/Mariner/Cookie/CookieServiceProvider.php
    title: CookieServiceProvider
  - id: encrypt
    resource: src/Mariner/Cookie/Middleware/EncryptCookies.php
    title: EncryptCookies
  - id: queued
    resource: src/Mariner/Cookie/Middleware/AddQueuedCookiesToResponse.php
    title: AddQueuedCookiesToResponse
  - id: f-cookie
    resource: src/Mariner/NutsAndBolts/Facades/Cookie.php
    title: Cookie facade
---

# Overview

`CookieJar` creates and queues cookies[^jar]; middleware encrypts cookies[^encrypt] and attaches queued cookies to responses[^queued].

`CookieServiceProvider` registers singleton `'cookie'`, built from `config('session')` keys `path`, `domain`, `secure`, `same_site`[^provider]. Facade `Cookie` → `'cookie'`[^f-cookie].

Voyager imports: `Voyager\NutsAndBolts\{ServiceProvider, DataObjects\Arr, Concerns\Macroable, Concerns\InteractsWithTime}`, `Voyager\Contracts\Encryption\{Encrypter, DecryptException}`.

# Open items

- Per-request state bound with `registerSingleton`, so one instance is shared across fibers: 'cookie' CookieServiceProvider.php:16.

[^jar]: CookieJar
[^provider]: CookieServiceProvider
[^encrypt]: EncryptCookies
[^queued]: AddQueuedCookiesToResponse
[^f-cookie]: Cookie facade
