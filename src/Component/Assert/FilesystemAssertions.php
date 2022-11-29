<?php

namespace Cinch\Component\Assert;

trait FilesystemAssertions
{
    private static string $noSuchFile = "file %s was expected to exist";

    /** Asserts that a path exists.
     * @return string asserted value
     * @throws AssertException $path is not a string or does not exist
     */
    public static function exists(mixed $path, string $message = ''): string
    {
        if (file_exists(static::string($path, $message)))
            return $path;

        static::fail($message, "path %s was expected to exist", static::strval($path));
    }

    /** Asserts that a file exists.
     * @return string asserted value
     * @throws AssertException $file is not a string or does not exist
     */
    public static function file(mixed $file, string $message = ''): string
    {
        if (is_file(static::string($file, $message)))
            return $file;

        static::fail($message, static::$noSuchFile, static::strval($file));
    }

    /** Asserts that a directory exists.
     * @return string asserted value
     * @throws AssertException $directory is not a string or does not exist
     */
    public static function directory(mixed $directory, string $message = ''): string
    {
        if (is_dir(static::string($directory, $message)))
            return $directory;

        static::fail($message, "directory %s was expected to exist", static::strval($directory));
    }

    /** Asserts that a file is readable.
     * @return string asserted value
     * @throws AssertException $file is not a string, does not exist or is not readable
     * @see static::minPermissions, static::maxPermissions, static::betweenPermissions
     */
    public static function readable(mixed $file, string $message = ''): string
    {
        if (is_readable(static::file($file, $message)))
            return $file;

        static::fail($message, "file %s was expected to be executable", static::strval($file));
    }

    /** Asserts that a file is writable.
     * @return string asserted value
     * @throws AssertException $file is not a string, does not exist or is not writable
     * @see static::minPermissions, static::maxPermissions, static::betweenPermissions
     */
    public static function writable(mixed $file, string $message = ''): string
    {
        if (is_writable(static::file($file, $message)))
            return $file;

        static::fail($message, "file %s was expected to be executable", static::strval($file));
    }

    /** Asserts that a file is executable.
     * @return string asserted value
     * @throws AssertException $file is not a string, does not exist or is not executable
     * @see static::minPermissions, static::maxPermissions, static::betweenPermissions
     */
    public static function executable(mixed $file, string $message = ''): string
    {
        if (is_executable(static::file($file, $message)))
            return $file;

        static::fail($message, "file %s was expected to be executable", static::strval($file));
    }

    /** Asserts that a file is at least permission limit.
     * @return string asserted value
     * @throws AssertException $file is not a string, does not exist or permissions below $limit
     * @see static::readable, static::writable, static::executable
     */
    public static function minPermissions(mixed $file, int $limit, string $message = ''): string
    {
        if (($perms = static::getPerms($file, $message)) >= $limit)
            return $file;

        static::fail($message, "file %s below minimum permissions %o, found %o",
            static::strval($file), $limit, $perms);
    }

    /** Asserts that a file does exceed permission limit.
     * @return string asserted value
     * @throws AssertException $file is not a string, does not exist or permissions exceed $limit
     * @see static::readable, static::writable, static::executable
     */
    public static function maxPermissions(mixed $file, int $limit, string $message = ''): string
    {
        if (($perms = static::getPerms($file, $message)) <= $limit)
            return $file;

        static::fail($message, "file %s exceeded maximum permissions %o, found %o",
            static::strval($file), $limit, $perms);
    }

    private static function getPerms(mixed $file, string $message): int
    {
        static::string($file, $message);

        if (($perms = fileperms($file)) === false)
            static::fail($message, static::$noSuchFile, static::strval($file));

        return $perms & 0777;
    }
}