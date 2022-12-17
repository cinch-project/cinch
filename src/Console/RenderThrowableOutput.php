<?php

namespace Cinch\Console;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** Application::doRenderThrowable outputs previous exceptions in verbose mode. There is no way to turn this off.
 * This class detects writeln(array) calls, which only occur as the first writeln() call per exception (including
 * previous). When the second writeln(array) is detected, it is ignored along with all future writes. This is
 * a fragile solution. It will break if future versions change the calling sequence. There **should** be an
 * 'Application::setRenderPreviousThrowables()` method.
 */
class RenderThrowableOutput extends ConsoleOutput
{
    private int $exceptionCount = 0;

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output->getVerbosity(), $output->isDecorated(), $output->getFormatter());
    }

    public function writeln(iterable|string $messages, int $options = self::OUTPUT_NORMAL): void
    {
        if ($this->exceptionCount > 1 || (is_iterable($messages) && ++$this->exceptionCount > 1))
            return;

        parent::writeln($messages, $options);
    }
}