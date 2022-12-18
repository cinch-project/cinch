<?php

namespace Cinch;

interface Io
{
    const UNDERLINE = 0x01;
    const BOLD = 0x02;
    const REVERSE = 0x04;
    const NEWLINE = 0x08;

    /** Outputs raw (non-formatted) text without a header.
     * @param string $message
     * @param array $context
     * @param bool $newLine
     */
    public function raw(string $message, array $context = [], bool $newLine = true): void;

    /** Outputs formatted text without a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function text(string $message, array $context = [], int $options = self::NEWLINE): void;

    /** Outputs formatted sub-text without a header. This is a secondary color for a sub-content.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function subtext(string $message, array $context = [], int $options = self::NEWLINE): void;

    /** Output a formatted warning with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function warning(string $message, array $context = [], int $options = self::NEWLINE): void;

    /** Output a formatted error with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function error(string $message, array $context = [], int $options = self::NEWLINE): void;

    /** Output a formatted notice with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function notice(string $message, array $context = [], int $options = self::NEWLINE): void;

    /** Output a formatted debug with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function debug(string $message, array $context = [], int $options = self::NEWLINE): void;
}