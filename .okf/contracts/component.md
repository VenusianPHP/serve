---
type: Module
title: Contracts
description: Interface groups for Mariner web components.
resource: src/Mariner/Contracts/Auth/Factory.php
tags: [contracts, interfaces]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: auth
    resource: src/Mariner/Contracts/Auth/Factory.php
    title: Auth\Factory
  - id: cookie
    resource: src/Mariner/Contracts/Cookie/QueueingFactory.php
    title: Cookie\QueueingFactory
  - id: core
    resource: src/Mariner/Contracts/Core/CachesRoutes.php
    title: Core\CachesRoutes
  - id: session
    resource: src/Mariner/Contracts/Session/Session.php
    title: Session\Session
  - id: routing
    resource: src/Mariner/Contracts/Routing/UrlGenerator.php
    title: Routing\UrlGenerator
  - id: validation
    resource: src/Mariner/Contracts/Validation/Validator.php
    title: Validation\Validator
  - id: view
    resource: src/Mariner/Contracts/View/Factory.php
    title: View\Factory
---

# Overview

Namespace `Mariner\Contracts\*`: interfaces only, no service provider or facade. Voyager imports: `Voyager\Contracts\NutsAndBolts\Arrayable`, `Voyager\Contracts\Queue\Factory`. Groups (file count):

- `Auth` (12): Authenticatable, CanResetPassword, Factory, Guard, StatefulGuard, SupportsBasicAuth, UserProvider, MustVerifyEmail, PasswordBroker, PasswordBrokerFactory, `Access\Gate`, `Middleware\AuthenticatesRequests`[^auth].
- `Cookie` (2): Factory, QueueingFactory[^cookie].
- `Core` (1): CachesRoutes (`routesAreCached`, `getCachedRoutesPath`)[^core].
- `Image` (2): Driver, Transformation. `JsonSchema` (1): JsonSchema.
- `Mail` (5): Attachable, Factory, MailQueue, Mailable, Mailer. `Notifications` (2): Dispatcher, Factory.
- `NutsAndBolts` (5): MessageBag, MessageProvider, Renderable, Responsable, ValidatedData.
- `Routing` (5): BindingRegistrar, Registrar, ResponseFactory, UrlGenerator, UrlRoutable[^routing].
- `Session` (2): Session, `Middleware\AuthenticatesSessions`[^session].
- `Translation` (3): HasLocalePreference, Loader, Translator.
- `Validation` (11): CompilableRules, DataAwareRule, Factory, ImplicitRule, InvokableRule, ParameterizedRule, Rule, UncompromisedVerifier, ValidationRule, Validator, ValidatorAwareRule[^validation].
- `View` (4): Engine, Factory, View, ViewCompilationException[^view].

[^auth]: Auth\Factory
[^cookie]: Cookie\QueueingFactory
[^core]: Core\CachesRoutes
[^session]: Session\Session
[^routing]: Routing\UrlGenerator
[^validation]: Validation\Validator
[^view]: View\Factory
