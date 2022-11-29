<?php

namespace Cinch\Console;

use Cinch\Project\ProjectId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;

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