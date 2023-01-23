<?php

namespace Cinch\Console;

use Cinch\Command\Migrate;
use Cinch\Command\MigrateOptions;
use Cinch\Command\Rollback;
use Cinch\Command\RollbackBy;
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

abstract class Command extends BaseCommand implements SignalableCommandInterface
{
    /* lookup table for commonly used options: see addOptionByName() */
    private const OPTIONS = [
        'env' => [null, InputOption::VALUE_REQUIRED, 'Sets the environment [default: project:environments.default]'],
        'tag' => [null, InputOption::VALUE_REQUIRED, 'Tag assigned to deployment [default: version 7 UUID]'],
        'deployer' => [null, InputOption::VALUE_REQUIRED, 'User or application performing deployment [default: current user]'],
        'store' => ['S', InputOption::VALUE_REQUIRED, 'Migration Store DSN', 'driver=fs store_dir=.'],
        'dry-run' => [null, InputOption::VALUE_NONE, 'Performs all actions and logging without executing [default: off]'],
    ];

    protected readonly ProjectId $projectId;
    protected readonly string $envName;
    protected readonly ConsoleLogger $logger;
    private readonly CommandBus $commandBus;
    private readonly bool $isDryRun;

    /**
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
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

    public function setHelp(string $help): static
    {
        $name = str_replace(':', '-', $this->getName());
        $url = "https://cinch.live/cli/commands/$name.html";

        if ($help) {
            if (str_ends_with($help, "\n"))
                $help .= "\n";
            else
                $help .= "\n\n";
        }

        /* if terminal supports links, <href> symfony tag will work, otherwise $url will display */
        return parent::setHelp("$help<href=\"$url\">$url</>");
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