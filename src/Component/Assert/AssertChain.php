<?php

namespace Cinch\Component\Assert;

use ReflectionException;
use ReflectionMethod;
use RuntimeException;

/**
 * TypeAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain int() Asserts that a value is an int.
 * @method AssertChain intOrNull() Asserts that a value is an int or null.
 * @method AssertChain string() Asserts that a value is a string.
 * @method AssertChain stringOrNull() Asserts that a value is a string or null.
 * @method AssertChain bool() Asserts that a value is a boolean.
 * @method AssertChain boolOrNull() Asserts that a value is a bool or null.
 * @method AssertChain true() Asserts that a value is boolean true.
 * @method AssertChain false() Asserts that a value is boolean false.
 * @method AssertChain float() Asserts that a value is a float.
 * @method AssertChain floatOrNull() Asserts that a value is a float or null.
 * @method AssertChain array() Asserts that a value is an array.
 * @method AssertChain arrayOrNull() Asserts that a value is an array or null.
 * @method AssertChain countable() Asserts that a value is countable: array or implements Countable.
 * @method AssertChain object() Asserts that a value is an object.
 * @method AssertChain objectOrNull() Asserts that a value is an object or null.
 * @method AssertChain class(string $class = '') Asserts that a value is an object of given class.
 * @method AssertChain resource() Asserts that a value is a resource.
 * @method AssertChain callable() Asserts that a value is a callable.
 * @method AssertChain null() Asserts that a value is a null.
 * @method AssertChain notNull() Asserts that a value is not null.
 *
 * ComparisonAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain greaterThan(mixed $limit) Asserts that a value is greater than given limit.
 * @method AssertChain greaterThanEqualTo(mixed $limit) Asserts that a value is less than or equal to given limit.
 * @method AssertChain lessThan(mixed $limit) Asserts that a value is less than given limit.
 * @method AssertChain lessThanEqualTo(mixed $limit) Asserts that a value is less than or equal to given limit.
 * @method AssertChain equals(mixed $value2) Asserts that a value is equal (identical) to given limit.
 * @method AssertChain between(mixed $min, mixed $max) Asserts that a value is between given minimum and maximum.
 * @method AssertChain empty() Asserts that a value is empty.
 * @method AssertChain notEmpty() Asserts that a value is not empty.
 *
 * FileSystemAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain exists() Asserts that a path exists.
 * @method AssertChain file() Asserts that a file exists.
 * @method AssertChain directory() Asserts that a directory exists.
 * @method AssertChain readable() Asserts that a file is readable.
 * @method AssertChain writable() Asserts that a file is writable.
 * @method AssertChain executable() Asserts that a file is executable.
 * @method AssertChain minPermissions(int $limit) Asserts that a file is at least permission limit.
 * @method AssertChain maxPermissions(int $limit) Asserts that a file does exceed permission limit.
 *
 * StringAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain length(int $length, string $encoding) Asserts that a string is given length.
 * @method AssertChain minLength(int $min, string $encoding) Asserts that a string is at least given minimum.
 * @method AssertChain maxLength(int $max, string $encoding) Asserts that a string does not exceed given maximum.
 * @method AssertChain betweenLength(int $min, int $max, string $encoding) Asserts that a string dis between given minimum and maximum.
 * @method AssertChain startsWith(string $needle, string $encoding) Asserts that a string starts with a set of characters.
 * @method AssertChain endsWith(string $needle, string $encoding) Asserts that a string ends with a set of characters.
 * @method AssertChain contains(string $needle, string $encoding) Asserts that a string contains a set of characters.
 * @method AssertChain notContains(string $needle, string $encoding) Asserts that a string does not contain a set of characters.
 * @method AssertChain regex(string $regex) Asserts that a string matches a regular expression.
 * @method AssertChain digit() Asserts that a string only contains ASCII digits.
 * @method AssertChain xdigit() Asserts that a string only contains hexadecimal digits.
 * @method AssertChain email() Asserts that a string is a valid RFC-5322 email address.
 * @method AssertChain url() Asserts that a string is a valid URL.
 * @method AssertChain date(string $format) Asserts that a string matches a given date format.
 * @method AssertChain host() Asserts that a string is a valid RFC-5322 hostname.
 * @method AssertChain ip() Asserts that a string is a valid IPv4 or IPv6 address.
 * @method AssertChain hostOrIp() Asserts that a string is a valid IPv4, IPv6 or hostname.
 *
 * ArrayAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain count(int $count) Asserts that a countable (array or Countable object) contains the given number of elements.
 * @method AssertChain key(string|int $key) Asserts that an array key exists.
 * @method AssertChain keySet(string|int $key) Asserts that an array key exists and it's value is not null.
 * @method AssertChain stringKey(string|int $key) Asserts that an array key exists with a string value.
 * @method AssertChain intKey(string|int $key) Asserts that an array key exists with a int value.
 * @method AssertChain floatKey(string|int $key) Asserts that an array key exists with a float value.
 * @method AssertChain boolKey(string|int $key) Asserts that an array key exists with a bool value.
 * @method AssertChain arrayKey(string|int $key) Asserts that an array key exists with a array value.
 * @method AssertChain objectKey(string|int $key) Asserts that an array key exists with a object value.
 * @method AssertChain in(string|array $choices) Asserts that an array contains a value.
 * @method AssertChain notIn(string|array $choices) Asserts that an array does not contains a value.
 *
 * ObjectAssertions
 * -----------------------------------------------------------------------------------
 * @method AssertChain prop(string $property) Asserts that an object contains the given property.
 * @method AssertChain propSet(string $property) Asserts that an object contains the given property and it's not null.
 * @method AssertChain stringProp(string $property) Asserts that an object contains the given string property.
 * @method AssertChain intProp(string $property) Asserts that an object contains the given int property.
 * @method AssertChain floatProp(string $property) Asserts that an object contains the given float property.
 * @method AssertChain boolProp(string $property) Asserts that an object contains the given bool property.
 * @method AssertChain arrayProp(string $property) Asserts that an object contains the given array property.
 * @method AssertChain objectProp(string $property) Asserts that an object contains the given object property.
 *
 * Assert (misc.)
 * -----------------------------------------------------------------------------------
 * @method AssertChain callback(callable $callback) Asserts that a value is valid using a callback.
 */
class AssertChain
{
    /** Creates an AssertChain.
     * @param string $assertClass
     * @param mixed $value
     * @param string $message
     * @throws RuntimeException if $assertClass is not a type of Cinch\Component\Assert\Assert
     */
    public function __construct(
        protected string $assertClass,
        private readonly mixed $value,
        private readonly string $message = '')
    {
        if (!$this->assertClass || !is_a($this->assertClass, Assert::class, true))
            throw new RuntimeException("AssertChain::assertClass must be a type of " . Assert::class);
    }

    /** Gets the value being asserted by this chain.
     * @note this ends the (fluent) chain
     * @return mixed
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * @throws AssertException
     * @throws RuntimeException if $name is not a valid Assert method or $arguments is the
     * wrong number of method parameters.
     */
    public function __call(string $name, array $arguments): self
    {
        try {
            $method = new ReflectionMethod($this->assertClass, $name);
        }
        catch (ReflectionException $e) {
            throw new RuntimeException("$this->assertClass::$name does not exist.", previous: $e);
        }

        /* value is always first parameter and message is always the last */
        array_unshift($arguments, $this->value);
        $arguments[] = $this->message;

        if (($n = $method->getNumberOfParameters() - count($arguments))) {
            $message = $n < 0 ? 'provided ' . -$n . ' extra' : "missing $n";
            throw new RuntimeException("$this->assertClass::$name $message argument(s)");
        }

        try {
            $method->invokeArgs(null, $arguments);
        }
        catch (ReflectionException $e) {
            throw new RuntimeException("$this->assertClass::$name - {$e->getMessage()}", previous: $e);
        }

        return $this;
    }
}