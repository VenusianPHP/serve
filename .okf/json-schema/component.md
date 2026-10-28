---
type: Module
title: JsonSchema
description: Fluent JSON Schema type builders with serializer and deserializer.
resource: src/Mariner/JsonSchema/JsonSchema.php
tags: [json-schema, types, mariner]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: jsonschema
    resource: src/Mariner/JsonSchema/JsonSchema.php
    title: JsonSchema
  - id: factory
    resource: src/Mariner/JsonSchema/JsonSchemaTypeFactory.php
    title: JsonSchemaTypeFactory
  - id: serializer
    resource: src/Mariner/JsonSchema/Serializer.php
    title: Serializer
  - id: deserializer
    resource: src/Mariner/JsonSchema/Deserializer.php
    title: Deserializer
  - id: type
    resource: src/Mariner/JsonSchema/Types/Type.php
    title: Type
  - id: contract
    resource: src/Mariner/Contracts/JsonSchema/JsonSchema.php
    title: Contracts\JsonSchema\JsonSchema
---

# Overview

`JsonSchema` entry point; `JsonSchema::fromArray()` builds a `Types\Type` from a raw schema array[^jsonschema]. `JsonSchemaTypeFactory` creates types[^factory]; `Serializer` and `Deserializer` convert types to and from arrays[^serializer][^deserializer].

Types: AnyOfType, ArrayType, BooleanType, IntegerType, NumberType, ObjectType, StringType, UnionType, all on base `Type`[^type]. Contract: `Mariner\Contracts\JsonSchema\JsonSchema`[^contract].

No service provider, no facade, no `Voyager\*` imports, no `config()` reads.

# Open items

- Laravel wording: Deserializer.php:44, JsonSchema.php:21.

[^jsonschema]: JsonSchema
[^factory]: JsonSchemaTypeFactory
[^serializer]: Serializer
[^deserializer]: Deserializer
[^type]: Type
[^contract]: Contracts\JsonSchema\JsonSchema
