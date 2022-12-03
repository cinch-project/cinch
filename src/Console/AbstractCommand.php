<?php

namespace Cinch\Console;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Component\Assert\Assert;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectName;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Command implements SignalableCommandInterface
{
    protected readonly LoggerInterface $logger;
    protected readonly ProjectId $projectId;
    protected readonly string $environmentName;

    public function setEnvironment(string $name): void
    {
        $this->environmentName = $name;
    }

    public function setProjectDir(string $projectDir): void
    {
        $this->projectId = new ProjectId($projectDir);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function addProjectArgument(): static
    {
        return $this->addArgument('project', InputArgument::REQUIRED, 'The project name');
    }

    protected function addTagOption(): static
    {
        return $this->addOption('tag', null,
            InputOption::VALUE_REQUIRED, 'Tags the deployment (recommended)');
    }

    protected function getEnvironmentFromInput(InputInterface $input, ProjectName $projectName): Environment
    {
        $target = $input->getArgument('target');
        $history = $input->getOption('history') ?: $target;
        $defaultSchema = sprintf(Environment::DEFAULT_SCHEMA_FORMAT, $projectName->value);

        return new Environment(
            new Dsn($target),
            new Dsn($history),
            $input->getOption('schema') ?: $defaultSchema,
            $input->getOption('table-prefix'),
            $this->getIntOption($input, 'deploy-lock-timeout'),
            $input->getOption('auto-create-schema')
        );
    }

    protected function getIntOption(InputInterface $input, string $name, int $default = 0): int
    {
        if (($value = $input->getOption($name)) === false)
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