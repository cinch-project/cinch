<?php

// avoid function_exists checks so we "actually" see redefinition errors

function silent_call(callable $c, ...$args): mixed
{
    static $mask = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_STRICT;

    $old = error_reporting();
    error_reporting($old & ~$mask);

    try {
        $result = $c(...$args);
    }
    catch (Throwable) {
        $result = null;
    }

    error_reporting($old);
    return $result;
}

/** Slurps a file into a string. */
function slurp(string $path, string $exceptionClass = Exception::class): string
{
    if (($content = @file_get_contents($path)) === false)
        throw new $exceptionClass(error_get_last()['message']);
    return $content;
}

function classname(string $class): string
{
    if (($pos = strrpos($class, '\\')) !== false)
        $class = substr($class, $pos + 1);
    return $class;
}

/** Recursively converts all objects to arrays.
 * @param object|array $value
 * @return array
 */
function arrayify(object|array $value): array
{
    $result = [];
    foreach ($value as $k => $v)
        $result[$k] = is_array($v) || is_object($v) ? arrayify($v) : $v;
    return $result;
}

function snakecase(string $camel): string
{
    return strtolower(preg_replace('~(?<!^)[A-Z]~', '_$0', $camel));
}

function camelcase(string $input, string $delimiter = '_'): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace($delimiter, ' ', $input))));
}

function nanosleep(int $nanoseconds): bool
{
    $seconds = floor($nanoseconds / 1e9);
    $nanoseconds = $nanoseconds % 1e9;

    while (is_array($r = time_nanosleep($seconds, $nanoseconds)))
        extract($r); // ['seconds' => 0, 'nanoseconds' => 0]

    return $r;
}

/** Gets local system time zone, nothing to do with php or php.ini.
 * @return string
 */
function system_time_zone(): string
{
    $tz = 'UTC';

    if (PHP_OS_FAMILY == 'Windows') {
        $cmd = 'powershell.exe [Windows.Globalization.Calendar,Windows.Globalization,' .
            'ContentType=WindowsRuntime]::New().GetTimeZone()';

        if (($r = @shell_exec($cmd)) && ($r = trim($r)))
            $tz = $r;
    }
    /* IANA TZ database: bsd, linux, mac, etc. "/etc/localtime" is a symlink to the TZ "zoneinfo" directory. */
    else if (($path = @readlink('/etc/localtime')) !== false) {
        /* after zoneinfo/ is the tzname: could be America/New_York for example. */
        if (($pos = strrpos($path, '/zoneinfo/')) !== false)
            $tz = substr($path, $pos + 10);
    }

    return $tz;
}

/** Gets the system user, not the php script owner.
 * @return string
 */
function system_user(): string
{
    if (PHP_OS_FAMILY == 'Windows') {
        /* one of these should always exist */
        if (($user = getenv('USERPROFILE')) || ($user = getenv('HOMEPATH')))
            return basename(str_replace('\\', '/', $user));

        /* in some odd IE restart cases, this could be unset. */
        if (($user = getenv('USERNAME')))
            return $user;

        /* this should be script owner but on windows, it is process owner. not sure how consistent
         * this is since the docs say script (file) owner. Tested on Windows 10 Pro.
         */
        return get_current_user();
    }

    if (($user = getenv('USER')))
        return $user;

    if (($user = getenv('HOME')))
        return basename($user);

    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid'))
        return posix_getpwuid(posix_geteuid())['name'];

    if (($user = shell_exec('whoami')))
        return $user;

    /* should never happen */
    throw new RuntimeException("cannot determine current user: tried USER, HOME, getpwnam(3), whoami");
}
