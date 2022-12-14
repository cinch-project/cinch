<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Environment\RemoveEnvironment;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:remove', 'Removes an environment')]
class EnvRemove extends AbstractCommand
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('This does cool stuff')
            ->addProjectArgument()
            ->addArgument('name', InputArgument::REQUIRED, 'Environment name')
            ->addOption('drop-history', 'D', InputOption::VALUE_NONE, 'Drop history schema');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);

        $name = $input->getArgument('name');
        $drop = $input->getOption('drop-history');

        $dropMsg = $drop ? 'and dropping history schema' : '';
        $this->logger->info("deleting environment $name $dropMsg");
        $this->commandBus->handle(new RemoveEnvironment($project, $name, $drop));

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }
}