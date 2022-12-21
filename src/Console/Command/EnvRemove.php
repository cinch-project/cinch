<?php

namespace Cinch\Console\Command;

use Cinch\Command\RemoveEnvironment;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:remove', 'Remove an environment')]
class EnvRemove extends ConsoleCommand
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $drop = $input->getOption('drop-history');

        $dropMsg = $drop ? 'and dropping history schema' : '';
        $this->io->text("deleting environment $name $dropMsg");

        $this->executeCommand(
            new RemoveEnvironment($this->projectId, $name, $drop),
            "Removing environment '$name' from project '" . $input->getArgument('project') . "'"
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
            ->addOption('drop-history', 'D', InputOption::VALUE_NONE, 'Drop history schema');
    }
}