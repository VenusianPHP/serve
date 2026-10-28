<?php

namespace Mariner\Contracts\JsonSchema;

use Closure;

interface JsonSchema
{
    /**
     * Create a new object schema instance.
     *
     * @param  (Closure(JsonSchema): array<string, \Mariner\JsonSchema\Types\Type>)|array<string, \Mariner\JsonSchema\Types\Type>  $properties
     * @return \Mariner\JsonSchema\Types\ObjectType
     */
    public function object(Closure|array $properties = []);

    /**
     * Create a new array property instance.
     *
     * @return \Mariner\JsonSchema\Types\ArrayType
     */
    public function array();

    /**
     * Create a new string property instance.
     *
     * @return \Mariner\JsonSchema\Types\StringType
     */
    public function string();

    /**
     * Create a new integer property instance.
     *
     * @return \Mariner\JsonSchema\Types\IntegerType
     */
    public function integer();

    /**
     * Create a new number property instance.
     *
     * @return \Mariner\JsonSchema\Types\NumberType
     */
    public function number();

    /**
     * Create a new boolean property instance.
     *
     * @return \Mariner\JsonSchema\Types\BooleanType
     */
    public function boolean();

    /**
     * Create a new multi-type union instance.
     *
     * @param  array<int, string>  $types
     * @return \Mariner\JsonSchema\Types\UnionType
     */
    public function union(array $types);

    /**
     * Create a new anyOf schema instance.
     *
     * @param  (Closure(JsonSchema): array<int, \Mariner\JsonSchema\Types\Type>)|array<int, \Mariner\JsonSchema\Types\Type>  $schemas
     * @return \Mariner\JsonSchema\Types\AnyOfType
     */
    public function anyOf(Closure|array $schemas);
}
