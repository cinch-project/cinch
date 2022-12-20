<?php

namespace Cinch\Console;

use Cinch\Io;
use DateTimeInterface;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleIo implements Io
{
    protected const formatMap = [
        'raw' => ['header' => '', 'message' => '<options=%s>%s</>'],
        'text' => ['header' => '', 'message' => '<fg=green;options=%s>%s</>'],
        'subtext' => ['header' => '', 'message' => '<fg=blue;options=%s>%s</>'],
        'warning' => ['header' => '<fg=black;bg=yellow> %s </> ', 'message' => '<options=%s>%s</>'],
        'error' => ['header' => '<fg=white;bg=red> %s </> ', 'message' => '<options=%s>%s</>'],
        'notice' => ['header' => '<fg=white;bg=blue> %s </> ', 'message' => '<options=%s>%s</>'],
        'debug' => ['header' => '<fg=white;bg=magenta> %s </> ', 'message' => '<options=%s>%s</>']
    ];

    private int $indent = 0;
    private bool $isNewLine = true;

    public function __construct(private readonly InputInterface $input, private readonly ConsoleOutputInterface $output)
    {
        $output->getFormatter()->setStyle('code-comment', new OutputFormatterStyle('gray'));
        $output->getFormatter()->setStyle('code', new OutputFormatterStyle('blue'));
    }

    public function getInput(): InputInterface
    {
        return $this->input;
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

    public function blank(int $count = 1): static
    {
        return $count < 1 ? $this : $this->raw(str_repeat("\n", $count), newLine: false);
    }

    public function raw(string $message = '', array $context = [], bool $newLine = true): static
    {
        return $this->write('raw', $message, $context, $newLine ? self::NEWLINE : 0);
    }

    public function text(string $message = '', array $context = [], int $options = self::NEWLINE): static
    {
        /* builtin info style, uses 'green' foreground */
        return $this->write('text', $message, $context, $options);
    }

    public function subtext(string $message = '', array $context = [], int $options = self::NEWLINE): static
    {
        return $this->write('subtext', $message, $context, $options);
    }

    public function warning(string $message, array $context = [], int $options = self::NEWLINE): static
    {
        return $this->write('warning', $message, $context, $options);
    }

    public function error(string $message, array $context = [], int $options = self::NEWLINE): static
    {
        /* builtin error style, white foreground with a red background */
        return $this->write('error', $message, $context, $options);
    }

    public function notice(string $message, array $context = [], int $options = self::NEWLINE): static
    {
        return $this->write('notice', $message, $context, $options);
    }

    public function debug(string $message, array $context = [], int $options = self::NEWLINE): static
    {
        return $this->write('debug', $message, $context, $options);
    }

    private function write(string $style, string $message, array $context, int $options): static
    {
        if ($this->suppress($style))
            return $this;

        $wantsNewLine = $options & self::NEWLINE;
        $message = $this->render($message, $context);

        if (!$message && !$wantsNewLine)
            return $this;

        $indent = $this->isNewLine && $this->indent ? str_repeat(' ', $this->indent) : '';

        /* track new lines for indent formatting */
        $this->isNewLine = str_ends_with($message, "\n") || $wantsNewLine;

        $message = $this->formatMessage($style, $message, $options);
        if ($wantsNewLine)
            $message .= "\n";

        $this->output->write($indent . $this->formatHeader($style) . $message);
        return $this;
    }

    private function formatHeader(string $style): string
    {
        if ($format = self::formatMap[$style]['header'])
            return sprintf($format, strtoupper($style));
        return '';
    }

    private function formatMessage(string $style, string $message, int $options): string
    {
        return sprintf(self::formatMap[$style]['message'], $this->formatOptions($options), $message);
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

    private function suppress(string $style): bool
    {
        return $this->output->isQuiet() ||
            ($style == 'debug' && !$this->output->isDebug()) ||
            ($style == 'notice' && $this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE);
    }

    private function render(string $message, $context): string
    {
        return preg_replace_callback('~{([^}\s]+)}~', static function ($m) use ($context) {
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