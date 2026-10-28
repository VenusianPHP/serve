---
okf_version: "0.2"
---

# Venusian serve

* [Mariner](orientation/mariner.md) - Laravel's web components under the Mariner namespace, built on individual venusian-voyager components; the framework never knows Mariner.
* [Static analysis](orientation/static-analysis.md) - PHPStan level 5 over src, no baseline: one reasoned phpstan.neon entry per suppressed cause.

# Http and routing

* [Http](http/component.md) - Request, response, uploaded-file, JSON resource and HTTP middleware classes on Symfony HttpFoundation.
* [Routing](routing/component.md) - Router, routes, URL generation, redirects, controller dispatch and routing middleware.
* [Core](core/component.md) - Mariner's Foundation layer, never split: the root provider, the HTTP kernel, the exception renderer and install:web.
* [Server](core/server.md) - php computer serve and HttpServer: HTTP/1.1 on a loop it is given, workers, and what threads share.

# Auth, session, cookie

* [Auth](auth/component.md) - Guards, user providers, authorization gate and password reset brokers.
* [Session](session/component.md) - Session manager, store, handlers and session middleware.
* [Cookie](cookie/component.md) - Cookie jar with queued cookies and encrypt/queue middleware.

# Views, translation, mail

* [View](view/component.md) - View factory, finder, engines and the Blade compiler.
* [Translation](translation/component.md) - Translator with file and array loaders and pluralization selector.
* [Mail](mail/component.md) - Mailer, mail manager, mailables, markdown mail and Symfony-transport drivers.
* [Notifications](notifications/component.md) - Notification channel manager and sender with mail, database and broadcast channels.

# Validation, schema, images

* [Validation](validation/component.md) - Validator factory, rule objects and presence verifiers for Mariner.
* [JsonSchema](json-schema/component.md) - Fluent JSON Schema type builders with serializer and deserializer.
* [Image](image/component.md) - Image manager, driver-backed image pipeline, transformations and output options.

# Support and testing

* [NutsAndBolts](nuts-and-bolts/component.md) - Web support classes, testing fakes and Mariner's facades.
* [Contracts](contracts/component.md) - Interface groups for Mariner web components.
* [Testing](testing/component.md) - HTTP test response, view/component and fluent JSON assertions.
