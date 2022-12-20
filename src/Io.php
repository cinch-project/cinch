<?php

namespace Cinch;

interface Io
{
    const UNDERLINE = 0x01;
    const BOLD = 0x02;
    const REVERSE = 0x04;
    const NEWLINE = 0x08;

    public function getIndent(): int;

    /** Sets the indent spacing.
     * @param int $count number of spaces
     * @return $this
     */
    public function setIndent(int $count = 0): static;

    /** Outputs one or more blank lines.
     * @return $this
     */
    public function blank(int $count = 1): static;

    /** Outputs raw (non-formatted) text without a header.
     * @param string $message
     * @param array $context
     * @param bool $newLine
     */
    public function raw(string $message = '', array $context = [], bool $newLine = true): static;

    /** Outputs formatted text without a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function text(string $message = '', array $context = [], int $options = self::NEWLINE): static;

    /** Outputs formatted sub-text without a header. This is a secondary color for a sub-content.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function subtext(string $message = '', array $context = [], int $options = self::NEWLINE): static;

    /** Output a formatted warning with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function warning(string $message, array $context = [], int $options = self::NEWLINE): static;

    /** Output a formatted error with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function error(string $message, array $context = [], int $options = self::NEWLINE): static;

    /** Output a formatted notice with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function notice(string $message, array $context = [], int $options = self::NEWLINE): static;

    /** Output a formatted debug with a header.
     * @param string $message
     * @param array $context
     * @param int $options
     */
    public function debug(string $message, array $context = [], int $options = self::NEWLINE): static;
}