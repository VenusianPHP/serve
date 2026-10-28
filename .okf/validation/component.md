---
type: Module
title: Validation
description: Validator factory, rule objects and presence verifiers for Mariner.
resource: src/Mariner/Validation/Factory.php
tags: [validation, rules, mariner]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: factory
    resource: src/Mariner/Validation/Factory.php
    title: Factory
  - id: validator
    resource: src/Mariner/Validation/Validator.php
    title: Validator
  - id: provider
    resource: src/Mariner/Validation/ValidationServiceProvider.php
    title: ValidationServiceProvider
  - id: rule
    resource: src/Mariner/Validation/Rule.php
    title: Rule
  - id: facade
    resource: src/Mariner/NutsAndBolts/Facades/Validator.php
    title: Validator facade
  - id: contracts
    resource: src/Mariner/Contracts/Validation/Factory.php
    title: Contracts\Validation\Factory
  - id: precognition
    resource: src/Mariner/Validation/Precognition.php
    title: Precognition
  - id: validation-helpers
    resource: src/Mariner/Validation/helpers.php
    title: Validation helpers
---

# Overview

Validator factory, validator, rule builders (`Rules\*`), database presence verifier and Have I Been Pwned password verifier[^factory][^validator][^rule]. Contracts live in `Mariner\Contracts\Validation` (Factory, Validator, Rule, ValidationRule, ImplicitRule, DataAwareRule, ValidatorAwareRule, InvokableRule, ParameterizedRule, CompilableRules, UncompromisedVerifier)[^contracts].

Deferred provider; binds via `registerSingleton`: `validator` (Factory over `$app['translator']`, presence verifier set when `db` and `validation.presence` are set), `validation.presence` (DatabasePresenceVerifier over `$app['db']`), `Mariner\Contracts\Validation\UncompromisedVerifier` (NotPwnedVerifier over `Voyager\Http\Client\Factory`)[^provider].

Facade: `Mariner\NutsAndBolts\Facades\Validator`, accessor `validator`[^facade].

Voyager imports: `Voyager\NutsAndBolts` (ServiceProvider, Str, Collection, Fluent, DataObjects\Arr/Number/Stringable, Concerns\Conditionable/Macroable, Exceptions\MathException), `Voyager\Contracts\NutsAndBolts` (Arrayable, DeferrableProvider), `Voyager\Contracts\Vessel\TheServiceContainer`, `Voyager\Vessel\ControlPanel`, `Voyager\Database` (ConnectionResolverInterface, Instrument\Model), `Voyager\Http\Client\Factory`[^provider][^validator]. No `config()` reads.

`Precognition::afterValidationHook($request)` is the precognitive-request hook. Validation's `helpers.php` defines `validator()` and `precognitive()`, each behind `function_exists`.[^precognition][^validation-helpers]

# Async

`Validator::async(?pool)` (`8b6ec1a`) validates a copy in a loop task: a Promise of `validate()`'s result, rejected with its `ValidationException`. In the copy, unique/exists counts run on a worker pool (`DatabasePresenceVerifier::via`) and the breached-password request rides the event loop (`NotPwnedVerifier::async`); `Password` asks its validator for the verifier. Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

[^factory]: Factory
[^validator]: Validator
[^provider]: ValidationServiceProvider
[^rule]: Rule
[^facade]: Validator facade
[^contracts]: Contracts\Validation\Factory
[^precognition]: Precognition
[^validation-helpers]: Validation helpers
