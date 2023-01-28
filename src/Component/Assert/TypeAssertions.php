<?php

namespace Cinch\Component\Assert;

use Countable;

trait TypeAssertions
{
    /** @throws AssertException */
    protected static function typeof(mixed $value): string
    {
        return "'" . get_debug_type($value) . "'";
    }

    /** Asserts that a value is a string.
     * @return string asserted value
     * @throws AssertException
     */
    public static function string(mixed $value, string $message = ''): string
    {
        if (is_string($value))
            return $value;
        static::fail($message, "expected string, found %s", static::typeof($value));
    }

    /** Asserts that a value is a string or null.
     * @return string|null asserted value
     * @throws AssertException
     */
    public static function stringOrNull(mixed $value, string $message = ''): string|null
    {
        if (is_string($value) || is_null($value))
            return $value;
        static::fail($message, "expected string|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is an int.
     * @return int asserted value
     * @throws AssertException
     */
    public static function int(mixed $value, string $message = ''): int
    {
        if (is_int($value))
            return $value;
        static::fail($message, "expected int, found %s", static::typeof($value));
    }

    /** Asserts that a value is an int or null.
     * @return int|null asserted value
     * @throws AssertException
     */
    public static function intOrNull(mixed $value, string $message = ''): int|null
    {
        if (is_int($value) || is_null($value))
            return $value;
        static::fail($message, "expected int|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is a float.
     * @return float asserted value
     * @throws AssertException
     */
    public static function float(mixed $value, string $message = ''): float
    {
        if (is_float($value))
            return $value;
        static::fail($message, "expected float, found %s", static::typeof($value));
    }

    /** Asserts that a value is a float or null.
     * @return float|null asserted value
     * @throws AssertException
     */
    public static function floatOrNull(mixed $value, string $message = ''): float|null
    {
        if (is_float($value) || is_null($value))
            return $value;
        static::fail($message, "expected float|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is a boolean.
     * @return bool asserted value
     * @throws AssertException
     */
    public static function bool(mixed $value, string $message = ''): bool
    {
        if (is_bool($value))
            return $value;
        static::fail($message, "expected bool, found %s", static::typeof($value));
    }

    /** Asserts that a value is a bool or null.
     * @return bool|null asserted value
     * @throws AssertException
     */
    public static function boolOrNull(mixed $value, string $message = ''): bool|null
    {
        if (is_bool($value) || is_null($value))
            return $value;
        static::fail($message, "expected bool|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is boolean true.
     * @return bool asserted value, always true
     * @throws AssertException
     */
    public static function true(mixed $value, string $message = ''): bool
    {
        if ($value === true)
            return true;
        static::fail($message, "expected to be true");
    }

    /** Asserts that a value is boolean false.
     * @return bool asserted value, always false
     * @throws AssertException
     */
    public static function false(mixed $value, string $message = ''): bool
    {
        if ($value === false)
            return false;
        static::fail($message, "expected to be false");
    }

    /** Asserts that a value is a null.
     * @return null asserted value, always null
     * @throws AssertException
     */
    public static function null(mixed $value, string $message = ''): mixed
    {
        if (is_null($value))
            return null;
        static::fail($message, "expected null, found %s", static::typeof($value));
    }

    /** Asserts that a value is not null.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function notNull(mixed $value, string $message = ''): mixed
    {
        if (!is_null($value))
            return $value;
        static::fail($message, "expected non-null value");
    }

    /** Asserts that a value is an array.
     * @return array asserted value
     * @throws AssertException
     */
    public static function array(mixed $value, string $message = ''): array
    {
        if (is_array($value))
            return $value;
        static::fail($message, "expected array, found %s", static::typeof($value));
    }

    /** Asserts that a value is an array or null.
     * @return array|null asserted value
     * @throws AssertException
     */
    public static function arrayOrNull(mixed $value, string $message = ''): array|null
    {
        if (is_array($value) || is_null($value))
            return $value;
        static::fail($message, "expected array|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is countable: array or implements Countable.
     * @return array|Countable asserted value
     * @throws AssertException
     */
    public static function countable(mixed $value, string $message = ''): array|Countable
    {
        if (is_countable($value))
            return $value;
        static::fail($message, "expected countable, found %s", static::typeof($value));
    }

    /** Asserts that a value is an object.
     * @return object asserted value
     * @throws AssertException
     */
    public static function object(mixed $value, string $message = ''): object
    {
        if (is_object($value))
            return $value;
        static::fail($message, "expected object, found %s", static::typeof($value));
    }

    /** Asserts that a value is an object or null.
     * @return object|null asserted value
     * @throws AssertException
     */
    public static function objectOrNull(mixed $value, string $message = ''): object|null
    {
        if (is_object($value) || is_null($value))
            return $value;
        static::fail($message, "expected object|null, found %s", static::typeof($value));
    }

    /** Asserts that a value is an object of given class.
     * @param mixed $value
     * @param string $class
     * @param string $message
     * @return object asserted value
     */
    public static function class(mixed $value, string $class, string $message = ''): object
    {
        if (is_object($value) && is_a($value, $class))
            return $value;
        static::fail($message, "expected %s, found %s", static::strval($class), static::typeof($value));
    }

    /** Asserts that a value is a resource.
     * @return resource asserted value
     * @throws AssertException
     */
    public static function resource(mixed $value, string $message = '')
    {
        if (is_resource($value))
            return $value;
        static::fail($message, "expected resource, found %s", static::typeof($value));
    }

    /** Asserts that a value is a callable.
     * @return callable asserted value
     * @throws AssertException
     */
    public static function callable(mixed $value, string $message = ''): callable
    {
        if (is_callable($value))
            return $value;
        static::fail($message, "expected callable, found %s", static::typeof($value));
    }
}
