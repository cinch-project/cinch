<?php

namespace Cinch\Console\Command;

use Cinch\Command\Task\TaskEnded;
use Cinch\Command\Task\TaskStarted;
use Cinch\Component\Assert\Assert;
use Cinch\Console\ConsoleIo;
use Cinch\Event\ProgressEvent;
use Cinch\Project\ProjectId;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use League\Tactician\CommandBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ConsoleCommand extends Command implements SignalableCommandInterface, EventSubscriberInterface
{
    /* lookup table for commonly used options: see getOptionByName() */
    private const OPTIONS = [
        'env' => [null, InputOption::VALUE_REQUIRED, 'Sets the environment [default: project:environments.default]'],
        'tag' => [null, InputOption::VALUE_REQUIRED, 'Deployment tag [default: version 7 UUID]'],
        'deployer' => [null, InputOption::VALUE_REQUIRED, 'User or application performing deployment [default: current user]'],
        'migration-store' => ['m', InputOption::VALUE_REQUIRED, 'Migration Store DSN', '.'],
    ];

    protected readonly ProjectId $projectId;
    protected readonly string $envName;
    protected readonly ConsoleIo $io;
    private readonly CommandBus $commandBus;
    private readonly Terminal $terminal;

    /**
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->terminal = new Terminal();
        $this->envName = $input->hasOption('env') ? ($input->getOption('env') ?? '') : '';
    }

    public function setProjectDir(string $projectDir): void
    {
        $this->projectId = new ProjectId($projectDir);
    }

    public function setCommandBus(CommandBus $commandBus): void
    {
        $this->commandBus = $commandBus;
    }

    public function setConsoleIo(ConsoleIo $io): void
    {
        $this->io = $io;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskStarted::class => 'onTaskStarted',
            TaskEnded::class => 'onTaskEnded',
        ];
    }

    public function onTaskStarted(TaskStarted $event): void
    {
        static $counter = 0;

        if ($event->rollback)
            $number = '<error>[00]</>';
        else
            $number = sprintf('[%2d]', ++$counter);

        $msgWidth = $this->terminal->getWidth() - $this->io->getIndent() - 60;
        $message = sprintf("%s %-28s <fg=blue>%-{$msgWidth}s</>",
            $number,
            self::strtrunc($event->name, 28),
            self::strtrunc($event->message, $msgWidth)
        );

        $this->io->raw($message, newLine: false);
    }

    public function onTaskEnded(TaskEnded $event): void
    {
        $statusColor = $event->success ? 'green' : 'red';
        $this->io->text(sprintf(' <fg=%s>%s</> <fg=gray>%.3fs</>',
            $statusColor,
            $event->success ? 'PASS' : 'FAIL',
            $event->elapsedSeconds
        ));
    }

    protected static function strtrunc(string $s, int $maxLength): string
    {
        if (strlen($s) > $maxLength)
            $s = substr($s, 0, $maxLength - 3) . '<fg=gray>...</>';
        return $s;
    }

    /**
     * @throws Exception
     */
    protected function executeCommand(string $title, object $command): void
    {
        $success = false;

        try {
            $this->io->text("$title\n")->setIndent(2);
            $this->commandBus->handle($command);
            $success = true;
        }
        finally {
            $this->io->setIndent();
            if ($success)
                $this->io->text("\ncompleted successfully");
        }
    }

    /**
     * @throws Exception
     */
    protected function executeQuery(object $query): mixed
    {
        return $this->commandBus->handle($query);
    }

    protected function addProjectArgument(): static
    {
        return $this->addArgument('project', InputArgument::REQUIRED, 'Project name');
    }

    protected function addOptionByName(string $name, string $description = ''): static
    {
        $args = self::OPTIONS[$name] ?? null;

        if (!$args)
            throw new RuntimeException("option '$name' does not exist");

        if ($description)
            $args[2] = $description;

        return $this->addOption($name, ...$args);
    }

    /**
     * @throws Exception
     */
    protected function parseDateValue(string $value): DateTimeInterface|null
    {
        if (preg_match('~([+\-]\d\d:?\d\d)$~', $value, $m)) {
            $timeZone = new DateTimeZone($m[1]);
            $value = substr($value, 0, -strlen($m[1]));
        }
        else {
            $timeZone = new DateTimeZone(get_system_time_zone());
        }

        $date = str_contains($value, '-');
        $time = str_contains($value, ':');

        if ($date && $time)
            $format = 'Y-m-d\TH:i:s';
        else if ($date)
            $format = 'Y-m-d';
        else if ($time)
            $format = 'H:i:s';
        else
            $format = '';

        if ($format) {
            Assert::date($value, $format, '(date) value argument');
            return new DateTimeImmutable($value, $timeZone);
        }

        return null;
    }

    protected function getIntOption(InputInterface $input, string $name, int $default = 0): int
    {
        $value = $input->getOption($name);
        if ($value === null || $value === false)
            return $default;
        return is_int($value) ? $value : Assert::digit($value, $name);
    }

    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal): never
    {
        $name = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            default => 'UNKNOWN' // never happen
        };

        echo "'{$this->getName()}' command interrupted by {$name}[$signal]\n";
        exit(0);
    }
}