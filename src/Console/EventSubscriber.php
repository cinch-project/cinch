<?php

namespace Cinch\Console;

use Cinch\Command\Event;
use Symfony\Component\Console\Terminal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    private readonly Terminal $terminal;

    public function __construct(private readonly ConsoleLogger $logger)
    {
        $this->terminal = new Terminal();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Event\TaskStarted::class => 'onTaskStarted',
            Event\TaskEnded::class => 'onTaskEnded'
        ];
    }

    public function onTaskStarted(Event\TaskStarted $task): void
    {
        static $nameWidth = 32;

        $descWidth = $this->terminal->getWidth() - $this->logger->getIndent() - 60;

        if ($task->isUndo)
            $message = sprintf("<fg=red>UNDO</> <fg=gray>%-{$nameWidth}s</> <fg=gray>%-{$descWidth}s</> ",
                "#$task->id $task->name",
                'undoing previous action...'
            );
        else
            $message = sprintf("<fg=yellow>(%2d)</> %-{$nameWidth}s %-{$descWidth}s ",
                $task->id,
                $this->strtrunc($task->name, $nameWidth),
                $this->strtrunc($task->description, $descWidth)
            );

        $this->logger->info($message, options: ConsoleLogger::RAW);
    }

    public function onTaskEnded(Event\TaskEnded $task): void
    {
        $elapsed = $task->elapsedSeconds;

        /* never display more than 2 digits for seconds, minutes and hours. no support for days. */

        // >5940 seconds (99 minutes): display format 12h47m
        if ($elapsed > 5940) {
            $min = (int) $elapsed / 60;
            $hour = $min / 60;
            $min = (int) $min % 60;
            $elapsed = sprintf('%dh%02dm', $hour, $min);
        }
        // >99 seconds: display format 12m47s
        else if ($elapsed > 99) {
            $sec = (int) $elapsed;
            $min = $sec / 60;
            $sec = $sec % 60;
            $elapsed = sprintf('%dm%02ds', $min, $sec);
        }
        // <=99 seconds: display format 12.472s
        else {
            $elapsed = sprintf('%.3fs', $elapsed);
        }

        $status = $task->success ? 'PASS' : 'FAIL';
        $statusColor = $task->success ? 'green' : 'red';
        $this->logger->info(
            sprintf('<fg=%s>%s</> <fg=gray>%s</>', $statusColor, $status, $elapsed),
            options: ConsoleLogger::RAW | ConsoleLogger::NEWLINE
        );
    }

    private function strtrunc(string $s, int $maxLength): string
    {
        if (strlen($s) > $maxLength)
            $s = substr($s, 0, $maxLength - 3) . '<fg=gray>...</>';
        return $s;
    }
}
