<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Environment\AddEnvironment;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:add', 'Adds an environment')]
class EnvAdd extends ConsoleCommand
{
    use AddsEnvironment;

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $input->getArgument('project');
        $newEnvName = $input->getArgument('name');

        $this->logger->info("adding environment $newEnvName to project $project");
        $this->dispatch(new AddEnvironment($this->projectId, $newEnvName, $this->getEnvironmentFromInput($input)));

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }

    protected function configure()
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Environment name')
            ->addTargetArgument()
            ->addEnvironmentOptions();
    }
}