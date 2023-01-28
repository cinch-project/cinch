<?php

namespace Cinch\Component\Assert;

trait ObjectAssertions
{
    /** Assert property exists and uses value in a chain.
     * @note this is a `property_exists` check
     * @param mixed $object must be an object
     * @param string $property property name
     * @param string $message
     * @return AssertChain
     */
    public static function thatProp(mixed $object, string $property, string $message = ''): AssertChain
    {
        return static::that(static::prop($object, $property, $message), $message);
    }

    /** Assert property exists and and it's not null and uses value in a chain.
     * @note this is a `isset` check
     * @param mixed $object must be an object
     * @param string $property property name
     * @param string $message
     * @return AssertChain
     */
    public static function thatPropSet(mixed $object, string $property, string $message = ''): AssertChain
    {
        return static::that(static::propSet($object, $property, $message), $message);
    }

    /** Use property value in a chain if it exists, otherwise use the given default.
     * @note this is a `property_exists` check
     * @param mixed $object must be an object
     * @param string $property property name
     * @param mixed $default value to use if property does not exist
     * @param string $message
     * @return AssertChain
     */
    public static function ifProp(mixed $object, string $property, mixed $default, string $message = ''): AssertChain
    {
        if (property_exists(static::object($object, $message), $property))
            $value = $object->$property;
        else
            $value = $default;

        return static::that($value, $message);
    }

    /** Use property value in a chain if it exists and it's not null, otherwise use the given default.
     * @note this is an `isset` check
     * @param mixed $object must be an object
     * @param string $property property name
     * @param mixed $default value to use if property does not exist or is null
     * @param string $message
     * @return AssertChain
     */
    public static function ifPropSet(mixed $object, string $property, mixed $default, string $message = ''): AssertChain
    {
        static::object($object, $message);
        return static::that($object->$property ?? $default, $message);
    }

    /** Asserts that an object contains the given property.
     * @return mixed property value
     * @throws AssertException
     */
    public static function prop(mixed $object, string $property, string $message = ''): mixed
    {
        if (property_exists(static::object($object, $message), $property))
            return $object->$property;

        static::fail($message, 'object %s does not contain property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given property and it's not null.
     * @return mixed property value
     * @throws AssertException
     */
    public static function propSet(mixed $object, string $property, string $message = ''): mixed
    {
        static::object($object, $message);

        if (isset($object->$property))
            return $object->$property;

        static::fail($message, 'object %s does not contain property %s or it is null',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given string property.
     * @return string property value
     * @throws AssertException
     */
    public static function stringProp(mixed $object, string $property, string $message = ''): string
    {
        if (property_exists(static::object($object, $message), $property))
            return static::string($object->$property, $message);

        static::fail($message, 'object %s does not contain string property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given int property.
     * @return int property value
     * @throws AssertException
     */
    public static function intProp(mixed $object, string $property, string $message = ''): int
    {
        if (property_exists(static::object($object, $message), $property))
            return static::int($object->$property, $message);

        static::fail($message, 'object %s does not contain int property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given float property.
     * @return float property value
     * @throws AssertException
     */
    public static function floatProp(mixed $object, string $property, string $message = ''): float
    {
        if (property_exists(static::object($object, $message), $property))
            return static::float($object->$property, $message);

        static::fail($message, 'object %s does not contain float property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given bool property.
     * @return bool property value
     * @throws AssertException
     */
    public static function boolProp(mixed $object, string $property, string $message = ''): bool
    {
        if (property_exists(static::object($object, $message), $property))
            return static::bool($object->$property, $message);

        static::fail($message, 'object %s does not contain bool property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given array property.
     * @return array property value
     * @throws AssertException
     */
    public static function arrayProp(mixed $object, string $property, string $message = ''): array
    {
        if (property_exists(static::object($object, $message), $property))
            return static::array($object->$property, $message);

        static::fail($message, 'object %s does not contain array property %s',
            static::strval($object), static::strval($property));
    }

    /** Asserts that an object contains the given object property.
     * @return object property value
     * @throws AssertException
     */
    public static function objectProp(mixed $object, string $property, string $message = ''): object
    {
        if (property_exists(static::object($object, $message), $property))
            return static::object($object->$property, $message);

        static::fail($message, 'object %s does not contain object property %s',
            static::strval($object), static::strval($property));
    }
}
