<?php

namespace Cinch\Console\Command;

use Cinch\Component\Assert\Assert;
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
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ConsoleCommand extends Command implements SignalableCommandInterface
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
    protected readonly SymfonyStyle $io;
    private readonly CommandBus $commandBus;

    /**
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
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

    /**
     * @throws Exception
     */
    protected function executeCommand(object $command): void
    {
        $this->commandBus->handle($command);
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