<?php

namespace Cinch\Console\Command;

use Cinch\Command\RemoveEnvironment;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:remove', 'Remove an environment')]
class EnvRemove extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $deleteHistory = $input->getOption('delete-history');

        $this->executeCommand(
            new RemoveEnvironment($this->projectId, $name, $deleteHistory),
            "Removing environment $name " . ($deleteHistory ? 'and deleting history' : '')
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
            ->addOption('delete-history', 'D', InputOption::VALUE_NONE, 'Delete history');
    }
}