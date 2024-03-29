<?php

namespace Cinch\Console\Command;

use Cinch\Command\AddEnvironment;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:add', 'Adds an environment')]
class EnvAdd extends Command
{
    use AddsEnvironment;

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newEnvName = $input->getArgument('name');

        $this->executeCommand(
            new AddEnvironment($this->projectName, $newEnvName, $this->getEnvironmentFromInput($input)),
            "Adding environment '$newEnvName' to project '" . $input->getArgument('project') . "'"
        );

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
            ->addProjectArgument()
            ->addArgument('name', InputArgument::REQUIRED, 'Environment name')
            ->addTargetArgument()
            ->addEnvironmentOptions()
            ->setHelp('');
    }
}
