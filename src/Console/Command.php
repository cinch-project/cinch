<?php

namespace Cinch\Console;

use Cinch\Command\Migrate;
use Cinch\Command\MigrateOptions;
use Cinch\Command\Rollback;
use Cinch\Command\RollbackBy;
use Cinch\Command\Task;
use Cinch\Common\Author;
use Cinch\Component\Assert\Assert;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use League\Tactician\CommandBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class Command extends BaseCommand implements SignalableCommandInterface, EventSubscriberInterface
{
    /* lookup table for commonly used options: see addOptionByName() */
    private const OPTIONS = [
        'env' => [null, InputOption::VALUE_REQUIRED, 'Sets the environment [default: project:environments.default]'],
        'tag' => [null, InputOption::VALUE_REQUIRED, 'Tag assigned to deployment [default: version 7 UUID]'],
        'deployer' => [null, InputOption::VALUE_REQUIRED, 'User or application performing deployment [default: current user]'],
        'migration-store' => ['m', InputOption::VALUE_REQUIRED, 'Migration Store DSN', 'driver=fs store_dir=.'],
        'dry-run' => [null, InputOption::VALUE_NONE, 'Performs all actions and logging without executing [default: off]'],
    ];

    protected readonly ProjectId $projectId;
    protected readonly string $envName;
    protected readonly ConsoleLogger $logger;
    private readonly CommandBus $commandBus;
    private readonly Terminal $terminal;
    private readonly bool $isDryRun;

    /**
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->terminal = new Terminal();
        $this->envName = $input->hasOption('env') ? ($input->getOption('env') ?? '') : '';
        $this->isDryRun = $input->hasOption('dry-run') && $input->getOption('dry-run') === true;
    }

    public function setProjectDir(string $projectDir): void
    {
        $this->projectId = new ProjectId($projectDir);
    }

    public function setCommandBus(CommandBus $commandBus): void
    {
        $this->commandBus = $commandBus;
    }

    public function setLogger(ConsoleLogger $logger): void
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Task\StartedEvent::class => 'onTaskStarted',
            Task\EndedEvent::class => 'onTaskEnded',
        ];
    }

    public function onTaskStarted(Task\StartedEvent $task): void
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
                self::strtrunc($task->name, $nameWidth),
                self::strtrunc($task->description, $descWidth)
            );

        $this->logger->info($message, options: ConsoleLogger::RAW);
    }

    public function onTaskEnded(Task\EndedEvent $task): void
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

    protected static function strtrunc(string $s, int $maxLength): string
    {
        if (strlen($s) > $maxLength)
            $s = substr($s, 0, $maxLength - 3) . '<fg=gray>...</>';
        return $s;
    }

    /**
     * @throws Exception
     */
    protected function executeMigrate(InputInterface $input, MigrateOptions $options, string $title = ''): void
    {
        $this->executeCommand(new Migrate(
            $this->projectId,
            new DeploymentTag($input->getOption('tag')),
            new Author($input->getOption('deployer') ?: system_user()),
            $options,
            $this->envName,
            $this->isDryRun,
        ), $title);
    }

    /**
     * @throws Exception
     */
    protected function executeRollback(InputInterface $input, RollbackBy $rollbackBy, string $title = ''): void
    {
        $this->executeCommand(new Rollback(
            $this->projectId,
            new DeploymentTag($input->getOption('tag')),
            new Author($input->getOption('deployer') ?: system_user()),
            $rollbackBy,
            $this->envName,
            $this->isDryRun,
        ), $title);
    }

    /**
     * @throws Exception
     */
    protected function executeCommand(object $command, string $title = ''): void
    {
        $success = false;

        if ($this->isDryRun)
            $this->logger->banner('[DRY RUN] The migration store, target database and ' .
                'history will not be changed', 'fg=black;bg=blue');

        $this->logger->info(($title ?: $this->getDescription()) . "\n");
        $this->logger->setIndent(2);

        try {
            $this->commandBus->handle($command);
            $success = true;
        }
        finally {
            $this->logger->setIndent();
            if ($success)
                $this->logger->info("\ncompleted successfully");
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
    protected function parseDateTime(string $datetime): DateTime
    {
        $value = $datetime;

        if (preg_match('~([+\-]\d\d:?\d\d)$~', $value, $m)) {
            $timeZone = new DateTimeZone($m[1]);
            $value = substr($value, 0, -strlen($m[1]));
        }
        else {
            $timeZone = new DateTimeZone(system_time_zone());
        }

        $hasDate = str_contains($value, '-');
        $colons = substr_count($value, ':');
        $hasTime = $colons > 0;

        /* auto-append seconds if not provided */
        if ($colons == 1)
            $value .= ':00';

        if ($hasDate && $hasTime)
            $format = 'Y-m-d\TH:i:s';
        else if ($hasDate)
            $format = 'Y-m-d';
        else if ($hasTime)
            $format = 'H:i:s';
        else
            return throw new InvalidArgumentException("invalid date: '$datetime'");

        return new DateTime(Assert::date($value, $format, 'date argument'), $timeZone);
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

        $this->logger->debug("'{$this->getName()}' command interrupted by {$name}[$signal]");
        exit(0);
    }
}