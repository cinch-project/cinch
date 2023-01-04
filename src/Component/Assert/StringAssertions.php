<?php

namespace Cinch\Component\Assert;

use DateTime;

trait StringAssertions
{
    /** Asserts that a string is given length.
     * @return string asserted value
     * @throws AssertException
     */
    public static function length(mixed $string, int $length, string $encoding = 'UTF-8', string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strlen($string, $encoding) == $length)
            return $string;

        static::fail($message, "value %s length != $length", static::strval($string));
    }

    /** Asserts that a string is at least given minimum.
     * @return string asserted value
     * @throws AssertException
     */
    public static function minLength(mixed $string, int $min, string $encoding = 'UTF-8', string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strlen($string, $encoding) >= $min)
            return $string;

        static::fail($message, "value %s length is not < $min", static::strval($string));
    }

    /** Asserts that a string does not exceed given maximum.
     * @return string asserted value
     * @throws AssertException
     */
    public static function maxLength(mixed $string, int $max, string $encoding = 'UTF-8', string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strlen($string, $encoding) <= $max)
            return $string;

        static::fail($message, "value %s length is not > $max", static::strval($string));
    }

    /** Asserts that a string is between given minimum and maximum.
     * @return string asserted value
     * @throws AssertException
     */
    public static function betweenLength(mixed $string, int $min, int $max, string $encoding = 'UTF-8',
        string $message = ''): string
    {
        static::string($string, $message);

        $len = mb_strlen($string, $encoding);
        if ($len >= $min && $len <= $max)
            return $string;

        static::fail($message, "value %s length not between $min and $max", static::strval($string));
    }

    /** Asserts that a string starts with a set of characters.
     * @return string asserted value
     * @throws AssertException
     */
    public static function startsWith(mixed $string, string $needle, string $encoding = 'UTF-8',
        string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strpos($string, $needle, encoding: $encoding) === 0)
            return $string;

        static::fail($message, "value %s does not start with %s", static::strval($string), static::strval($needle));
    }

    /** Asserts that a string ends with a set of characters.
     * @return string asserted value
     * @throws AssertException
     */
    public static function endsWith(mixed $string, string $needle, string $encoding = 'UTF-8',
        string $message = ''): string
    {
        static::string($string, $message);

        $pos = mb_strlen($string, $encoding) - mb_strlen($needle, $encoding);
        if ($pos >= 0 && mb_strrpos($string, $needle, encoding: $encoding) === $pos)
            return $string;

        static::fail($message, "value %s does not end with %s", static::strval($string), static::strval($needle));
    }

    /** Asserts that a string contains a set of characters.
     * @return string asserted value
     * @throws AssertException
     */
    public static function contains(mixed $string, string $needle, string $encoding = 'UTF-8',
        string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strpos($string, $needle, encoding: $encoding) !== false)
            return $string;

        static::fail($message, "value %s does not contain %s", static::strval($string), static::strval($needle));
    }

    /** Asserts that a string does not contain a set of characters.
     * @return string asserted value
     * @throws AssertException
     */
    public static function notContains(mixed $string, string $needle, string $encoding = 'UTF-8',
        string $message = ''): string
    {
        static::string($string, $message);

        if (mb_strpos($string, $needle, encoding: $encoding) === false)
            return $string;

        static::fail($message, "value %s contains %s", static::strval($string), static::strval($needle));
    }

    /** Asserts that a string matches a regular expression.
     * @return string asserted value
     * @throws AssertException
     */
    public static function regex(string $string, string $pattern, string $message = ''): string
    {
        if ($r = @preg_match($pattern, $string))
            return $string;

        static::fail($message, "value %s does not match %s %s",
            static::strval($string),
            static::strval($pattern),
            $r === false ? '- ' . error_get_last()['message'] : ''
        );
    }

    /** Asserts that a string only contains ASCII digits.
     * @return string asserted value
     * @throws AssertException
     */
    public static function digit(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (ctype_digit($string))
            return $string;

        static::fail($message, "value %s is not all digits", static::strval($string));
    }

    /** Asserts that a string only contains hexadecimal digits.
     * @return string asserted value
     * @throws AssertException
     */
    public static function xdigit(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (ctype_xdigit($string))
            return $string;

        static::fail($message, "value %s is not all hexadecimal digits", static::strval($string));
    }

    /** Asserts that a string is a valid RFC-5322 email address.
     * @return string asserted value
     * @throws AssertException
     */
    public static function email(mixed $string, string $message = ''): string
    {
        /* https://emailregex.com/ */
        static $pattern = <<<EMAIL
(?:[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])
EMAIL;
        static::string($string, $message);

        if (preg_match('/' . $pattern . '/Si', $string))
            return $string;

        static::fail($message, "value %s is not a valid email address", static::strval($string));
    }

    /** Asserts that a string matches a given date format.
     * @return string asserted value
     * @throws AssertException
     */
    public static function date(mixed $string, string $format, string $message = ''): string
    {
        static::string($string, $message);

        $dt = DateTime::createFromFormat($format, $string);
        if ($dt !== false && $dt->format($format) === $string)
            return $string;

        static::fail($message, "date %s does not match format %s", static::strval($string), static::strval($format));
    }

    /** Asserts that a string is a valid RFC-5322 hostname.
     * @return string asserted value
     * @throws AssertException
     */
    public static function host(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (filter_var($string, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false)
            return $string;

        static::fail($message, "value %s is not a valid hostname", static::strval($string));
    }

    /** Asserts that a string is a valid URL.
     * @return string asserted value
     * @throws AssertException
     */
    public static function url(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (filter_var($string, FILTER_VALIDATE_URL) !== false)
            return $string;

        static::fail($message, "value %s is not all digits", static::strval($string));
    }

    /** Asserts that a string is a valid IPv4 or IPv6 address.
     * @throws AssertException
     */
    public static function ip(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (filter_var($string, FILTER_VALIDATE_IP) !== false)
            return $string;

        static::fail($message, "value %s is not a valid IPv4 or IPv6 address", static::strval($string));
    }

    /** Asserts that a string is a valid IPv4, IPv6 or hostname.
     * @note useful for "host" parameters that can be an IP or hostname.
     * @return string the given $string
     * @throws AssertException
     */
    public static function hostOrIp(mixed $string, string $message = ''): string
    {
        static::string($string, $message);

        if (filter_var($string, FILTER_VALIDATE_IP) !== false ||
            filter_var($string, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false)
            return $string;

        static::fail($message, "value %s is not a valid IP address or a hostname", static::strval($string));
    }
}