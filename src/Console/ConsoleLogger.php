<?php

namespace Cinch\Console;

use Cinch\Component\Assert\AssertException;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger implements LoggerInterface
{
    const UNDERLINE = 0x01;
    const BOLD = 0x02;
    const REVERSE = 0x04;
    const NEWLINE = 0x08;
    /** Raw output, no formatting is automatically applied */
    const RAW = 0x10;

    protected const styleMap = [
        'raw' => ['header' => '', 'message' => ''],

        /* cinch never uses emergency, alert or critical. merely satisfying LoggerInterface */
        LogLevel::EMERGENCY => ['header' => '<fg=white;bg=red> %s </> ', 'message' => '<options=%s>%s</>'],
        LogLevel::ALERT => ['header' => '<fg=white;bg=red> %s </> ', 'message' => '<options=%s>%s</>'],
        LogLevel::CRITICAL => ['header' => '<fg=white;bg=red> %s </> ', 'message' => '<options=%s>%s</>'],

        LogLevel::ERROR => ['header' => '<fg=white;bg=red> %s </> ', 'message' => '<options=%s>%s</>'],
        LogLevel::WARNING => ['header' => '<fg=black;bg=yellow> %s </> ', 'message' => '<options=%s>%s</>'],
        LogLevel::NOTICE => ['header' => '<fg=white;bg=blue> %s </> ', 'message' => '<options=%s>%s</>'],
        LogLevel::INFO => ['header' => '', 'message' => '<fg=green;options=%s>%s</>'],
        LogLevel::DEBUG => ['header' => '<fg=white;bg=magenta> %s </> ', 'message' => '<options=%s>%s</>']
    ];

    private int $indent = 0;
    private bool $isNewLine = true;

    public function __construct(private readonly ConsoleOutputInterface $output)
    {
        $output->getFormatter()->setStyle('code-comment', new OutputFormatterStyle('gray'));
        $output->getFormatter()->setStyle('code', new OutputFormatterStyle('blue'));
    }

    public function getOutput(): ConsoleOutputInterface
    {
        return $this->output;
    }

    public function getIndent(): int
    {
        return $this->indent;
    }

    public function setIndent(int $count = 0): static
    {
        $this->indent = max(0, $count);
        return $this;
    }

    public function emergency(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context, $options);
    }

    public function alert(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::ALERT, $message, $context, $options);
    }

    public function critical(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context, $options);
    }

    public function error(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::ERROR, $message, $context, $options);
    }

    public function warning(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::WARNING, $message, $context, $options);
    }

    public function notice(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::NOTICE, $message, $context, $options);
    }

    public function info(string|Stringable $message = '', array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::INFO, $message, $context, $options);
    }

    public function debug(string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $this->log(LogLevel::DEBUG, $message, $context, $options);
    }

    public function log(mixed $level, string|Stringable $message, array $context = [], int $options = self::NEWLINE): void
    {
        $style = $level = $this->assertLogLevel($level);

        if ($options & self::RAW) {
            $style = 'raw';
            $options &= self::NEWLINE;
        }

        if ($this->suppress($level))
            return;

        $wantsNewLine = $options & self::NEWLINE;
        $message = $this->render($message, $context);

        if (!$message && !$wantsNewLine)
            return;

        $indent = $this->isNewLine && $this->indent ? str_repeat(' ', $this->indent) : '';

        /* track new lines for indent formatting */
        $this->isNewLine = str_ends_with($message, "\n") || $wantsNewLine;

        $message = $this->formatMessage($style, $message, $options);
        if ($wantsNewLine)
            $message .= "\n";

        $this->output->write($indent . $this->formatHeader($level, $style) . $message);
    }

    protected function assertLogLevel(mixed $level): string
    {
        if (!is_string($level) && !($level instanceof Stringable)) {
            $type = get_debug_type($level);
            $value = is_scalar($level) ? "$level ($type)" : $type;
            throw new AssertException("log level must be a string: found $value");
        }

        return match ($level) {
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG => $level,
            default => throw new AssertException("unknown log level '$level'")
        };
    }

    private function formatHeader(string $level, string $style): string
    {
        if ($style == 'raw') {
            if (self::styleMap[$level]['header'])
                return sprintf('[ %s ] ', strtoupper($level));
        }
        else if ($format = self::styleMap[$style]['header']) {
            return sprintf($format, strtoupper($level));
        }

        return '';
    }

    private function formatMessage(string $style, string $message, int $options): string
    {
        if ($format = self::styleMap[$style]['message'])
            return sprintf($format, $this->formatOptions($options), $message);
        return $message;
    }

    private function formatOptions(int $options): string
    {
        $opts = [];

        if ($options & self::BOLD)
            $opts[] = 'bold';

        if ($options & self::UNDERLINE)
            $opts[] = 'underscore';

        if ($options & self::REVERSE)
            $opts[] = 'reverse';

        return implode(',', $opts) ?: ','; // empty <options> doesn't work, <options=,> does
    }

    private function suppress(string $level): bool
    {
        return $this->output->isQuiet() ||
            ($level == LogLevel::DEBUG && !$this->output->isDebug()) ||
            ($level == LogLevel::NOTICE && $this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE);
    }

    private function render(string $message, $context): string
    {
        /* See section 1.2 for regex: https://www.php-fig.org/psr/psr-3/ */
        return preg_replace_callback('~{([a-zA-Z\d_.]+)}~', static function ($m) use ($context) {
            $v = $context[$m[1]] ?? '';

            if ($v === null || is_scalar($v) || $v instanceof Stringable)
                return $v ?? '';

            if ($v instanceof DateTimeInterface)
                return $v->format(DateTimeInterface::RFC3339);

            if (is_object($v))
                return get_class($v);

            if (is_array($v))
                return 'array[' . count($v) . ']';

            return '[' . get_debug_type($v) . ']';
        }, $message);
    }
}