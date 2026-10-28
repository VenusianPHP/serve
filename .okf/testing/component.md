---
type: Module
title: Testing
description: HTTP test response, view/component and fluent JSON assertions.
resource: src/Mariner/Testing/TestResponse.php
tags: [testing, assertions, mariner]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: response
    resource: src/Mariner/Testing/TestResponse.php
    title: TestResponse
  - id: view
    resource: src/Mariner/Testing/TestView.php
    title: TestView
  - id: component
    resource: src/Mariner/Testing/TestComponent.php
    title: TestComponent
  - id: json
    resource: src/Mariner/Testing/Fluent/AssertableJson.php
    title: AssertableJson
  - id: jsonstring
    resource: src/Mariner/Testing/AssertableJsonString.php
    title: AssertableJsonString
---

# Overview

`TestResponse` wraps a response with status, header, cookie, session, view and JSON assertions[^response]. `TestView` and `TestComponent` assert on rendered views and components[^view][^component]. `Fluent\AssertableJson` and `AssertableJsonString` provide JSON assertions[^json][^jsonstring]. Also: `Assert`, `TestResponseAssert`, `LoggedExceptionCollection`, `Constraints\{ArraySubset,SeeInHtml,SeeInOrder}`, `Concerns\AssertsStatusCodes`.

No service provider, no facade. Voyager imports: `Voyager\NutsAndBolts` (Collection, Str, DataObjects\Arr/Carbon, Concerns\Conditionable/Dumpable/Macroable/Tappable), `Voyager\Contracts\NutsAndBolts` (Arrayable, Jsonable), `Voyager\Contracts\Database\Query\Expression`, `Voyager\Database` (Connection, Instrument\Collection/Model)[^response]. No `config()` reads.

[^response]: TestResponse
[^view]: TestView
[^component]: TestComponent
[^json]: AssertableJson
[^jsonstring]: AssertableJsonString
