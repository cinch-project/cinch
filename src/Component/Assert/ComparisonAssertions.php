<?php

namespace Cinch\Component\Assert;

trait ComparisonAssertions
{
    /** Asserts that a value is less than given limit.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function lessThan(mixed $value, mixed $limit, string $message = ''): mixed
    {
        if ($value < $limit)
            return $value;

        static::fail($message, "value %s is not < %s", static::strval($value), static::strval($limit));
    }

    /** Asserts that a value is less than or equal to given limit.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function lessThanEqualTo(mixed $value, mixed $limit, string $message = ''): mixed
    {
        if ($value <= $limit)
            return $value;

        static::fail($message, "value %s is not <= %s", static::strval($value), static::strval($limit));
    }

    /** Asserts that a value is greater than given limit.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function greaterThan(mixed $value, mixed $limit, string $message = ''): mixed
    {
        if ($value > $limit)
            return $value;

        static::fail($message, "value %s is not > %s", static::strval($value), static::strval($limit));
    }

    /** Asserts that a value is less than or equal to given limit.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function greaterThanEqualTo(mixed $value, mixed $limit, string $message = ''): mixed
    {
        if ($value >= $limit)
            return $value;

        static::fail($message, "value %s is not >= %s", static::strval($value), static::strval($limit));
    }

    /** Asserts that a value is equal (identical) to given limit.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function equals(mixed $value, mixed $value2, string $message = ''): mixed
    {
        if ($value === $value2)
            return $value;

        static::fail($message, "value %s does not equal %s", static::strval($value), static::strval($value2));
    }

    /** Asserts that a value is between given minimum and maximum.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function between(mixed $value, mixed $min, mixed $max, string $message = ''): mixed
    {
        if ($value >= $min && $value <= $max)
            return $value;

        static::fail($message, "value %s is not between %s and %s",
            static::strval($value), static::strval($min), static::strval($min));
    }

    /** Asserts that a value is empty.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function empty(mixed $value, string $message = ''): mixed
    {
        if (!$value)
            return $value;

        static::fail($message, "value %s expected to be empty", static::strval($value));
    }

    /** Asserts that a value is not empty.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function notEmpty(mixed $value, string $message = ''): mixed
    {
        if ($value)
            return $value;

        static::fail($message, "value cannot be empty");
    }
}