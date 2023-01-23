<?php

namespace Cinch\Component\Assert;

use Countable;

trait ArrayAssertions
{
    /** Asserts array key exists and uses value in a chain.
     * @note this is an `array_key_exists` check
     * @param mixed $array must be an array
     * @param string|int $key key name
     * @param string $message
     * @return AssertChain
     */
    public static function thatKey(mixed $array, string|int $key, string $message = ''): AssertChain
    {
        return static::that(static::key($array, $key, $message), $message);
    }

    /** Asserts array key exists and and it's not null and uses value in a chain.
     * @note this is a `isset` check
     * @param mixed $array must be an array
     * @param string|int $key key name
     * @param string $message
     * @return AssertChain
     */
    public static function thatKeySet(mixed $array, string|int $key, string $message = ''): AssertChain
    {
        return static::that(static::keySet($array, $key, $message), $message);
    }

    /** Use key value in a chain if it exists, otherwise use the given default.
     * @note this is an `array_key_exists` check
     * @param mixed $array must be an array
     * @param string|int $key key name
     * @param mixed $default value to use if key does not exist
     * @param string $message
     * @return AssertChain
     */
    public static function ifKey(mixed $array, string|int $key, mixed $default, string $message = ''): AssertChain
    {
        if (array_key_exists($key, static::array($array, $message)))
            $value = $array[$key];
        else
            $value = $default;

        return static::that($value, $message);
    }

    /** Use key value in a chain if it exists and it's not null, otherwise use the given default.
     * @note this is an `isset` check
     * @param mixed $array must be an array
     * @param string|int $key key name
     * @param mixed $default value to use if key does not exist or is null
     * @param string $message
     * @return AssertChain
     */
    public static function ifKeySet(mixed $array, string|int $key, mixed $default, string $message = ''): AssertChain
    {
        static::array($array, $message);
        return static::that($array[$key] ?? $default, $message);
    }

    /** Asserts that a countable (array or Countable object) contains the given number of elements.
     * @return array|Countable asserted value
     * @throws AssertException
     */
    public static function count(mixed $array, int $count, string $message = ''): array|Countable
    {
        if (count(static::countable($array, $message)) == $count)
            return $array;

        static::fail($message, 'array does not contain %d elements', $count);
    }

    /** Asserts that an array key exists.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function key(mixed $array, string|int $key, string $message = ''): mixed
    {
        if (array_key_exists($key, static::array($array, $message)))
            return $array[$key];

        static::fail($message, 'array does not contain key %s', static::strval($key));
    }

    /** Asserts that an array key exists and it's value is not null.
     * @note this is an `isset` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function keySet(mixed $array, string|int $key, string $message = ''): mixed
    {
        static::array($array, $message);
        if (isset($array[$key]))
            return $array[$key];

        static::fail($message, 'array does not contain key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a string value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function stringKey(mixed $array, string|int $key, string $message = ''): string
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::string($array[$key], $message);

        static::fail($message, 'array does not contain string key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a int value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function intKey(mixed $array, string|int $key, string $message = ''): int
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::int($array[$key], $message);

        static::fail($message, 'array does not contain int key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a float value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function floatKey(mixed $array, string|int $key, string $message = ''): float
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::float($array[$key], $message);

        static::fail($message, 'array does not contain float key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a bool value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function boolKey(mixed $array, string|int $key, string $message = ''): bool
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::bool($array[$key], $message);

        static::fail($message, 'array does not contain bool key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a array value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function arrayKey(mixed $array, string|int $key, string $message = ''): array
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::array($array[$key], $message);

        static::fail($message, 'array does not contain array key %s', static::strval($key));
    }

    /** Asserts that an array key exists with a object value.
     * @note this is an `array_key_exists` check
     * @return mixed array key value
     * @throws AssertException
     */
    public static function objectKey(mixed $array, string|int $key, string $message = ''): object
    {
        if (array_key_exists($key, static::array($array, $message)))
            return static::object($array[$key], $message);

        static::fail($message, 'array does not contain object key %s', static::strval($key));
    }

    /** Asserts that an array contains a value.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function in(mixed $value, array $choices, string $message = ''): mixed
    {
        if (in_array($value, $choices))
            return $value;

        static::fail($message, 'array does not contain value %s', static::strval($value));
    }

    /** Asserts that an array does not contains a value.
     * @return mixed asserted value
     * @throws AssertException
     */
    public static function notIn(mixed $value, array $choices, string $message = ''): mixed
    {
        if (!in_array($value, $choices))
            return $value;

        static::fail($message, 'array cannot not contain value %s', static::strval($value));
    }
}