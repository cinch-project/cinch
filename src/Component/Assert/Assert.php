<?php

namespace Cinch\Component\Assert;

use DateTimeInterface;
use Stringable;

class Assert
{
    use TypeAssertions,
        StringAssertions,
        ComparisonAssertions,
        FilesystemAssertions,
        ArrayAssertions,
        ObjectAssertions;

    protected static AssertFactory $assertFactory;

    public static function setAssertFactory(AssertFactory $assertFactory): void
    {
        self::$assertFactory = $assertFactory;
    }

    /** Allows chaining one or more assertions.
     * @param mixed $value
     * @param string $message
     * @return AssertChain
     */
    public static function that(mixed $value, string $message = ''): AssertChain
    {
        return static::getAssertFactory()->createChain($value, $message);
    }

    /** Allows asserting multiple chains at once.
     * @return AssertMany
     */
    public static function many(): AssertMany
    {
        return static::getAssertFactory()->createMany();
    }

    /** Asserts that a value is valid using a callback.
     * @param callable $callback takes $value as the only parameter and returns a bool
     * @return mixed asserted value
     */
    public static function callback(mixed $value, callable $callback, string $message = ''): mixed
    {
        if ($callback($value) === true)
            return $value;

        static::fail($message, 'value %s did not pass custom validation');
    }

    protected static function strval(mixed $value): string
    {
        if (is_null($value))
            return 'null';

        if ($value instanceof Stringable)
            $value = (string) $value;
        else if ($value instanceof DateTimeInterface)
            $value = $value->format(DateTimeInterface::RFC3339);

        if (is_string($value)) {
            if (strlen($value) > 64)
                $value = substr($value, 0, 61) . '...';
            return "'$value'";
        }

        if (is_bool($value))
            return $value ? 'true' : 'false';

        if (is_int($value) || is_float($value))
            return $value;

        if (is_array($value))
            return 'array[' . count($value) . ']';

        if (is_object($value))
            return get_class($value);

        if (is_resource($value))
            return 'resource/' . get_resource_type($value);

        return static::typeof($value);
    }

    protected static function getAssertFactory(): AssertFactory
    {
        if (!isset(self::$assertFactory))
            static::setAssertFactory(new CinchAssertFactory());
        return self::$assertFactory;
    }

    /** @throws AssertException */
    protected static function fail(string $message, string $format, ...$args): void
    {
        if ($message) {
            $format = "%s: $format";
            array_unshift($args, $message);
        }

        throw static::getAssertFactory()->createException(sprintf($format, ...$args));
    }
}