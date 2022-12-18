<?php

namespace Cinch\Console;

use DateTimeInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger extends AbstractLogger
{
    private array $verbosityMap = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
    ];

    private array $formatMap = [
        LogLevel::EMERGENCY => 'error',
        LogLevel::ALERT => 'error',
        LogLevel::CRITICAL => 'error',
        LogLevel::ERROR => 'error',
        LogLevel::WARNING => 'warning',
        LogLevel::NOTICE => 'fg=blue',
        LogLevel::INFO => 'info',
        LogLevel::DEBUG => 'fg=magenta'
    ];

    public function __construct(private readonly OutputInterface $output)
    {
        $f = $this->output->getFormatter();
        $f->setStyle('warning', new OutputFormatterStyle('black', 'yellow'));
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (!isset($this->verbosityMap[$level]))
            throw new InvalidArgumentException("log level '$level'' does not exist.");

        // Write to the error output if necessary and available
        if ($this->formatMap[$level] == 'error' && $this->output instanceof ConsoleOutputInterface)
            $output = $this->output->getErrorOutput();
        else
            $output = $this->output;

        if ($output->getVerbosity() >= $this->verbosityMap[$level]) {
            $format = $this->formatMap[$level];
            $message = $this->render($message, $context);

            if ($level == LogLevel::NOTICE || $level == LogLevel::INFO)
                $output->writeln(sprintf('<%s>%s</>', $format, $message));
            else
                $output->writeln(sprintf('<%s>[%s] %s</>', $format, $level, $message));
        }
    }

    private function render(string $message, $context): string
    {
        return preg_replace_callback('~{([^}\s]+)}~', function ($m) use ($context) {
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