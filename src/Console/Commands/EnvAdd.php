<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Environment\AddEnvironment;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env:add', 'Adds an environment')]
class EnvAdd extends AbstractCommand
{
    use AddsEnvironment;

    public function __construct(private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);
        $name = $input->getArgument('name');
        $environment = $this->getEnvironmentFromInput($input, $project->getName());

        $this->logger->info("adding environment $name to project {$project->getName()}");
        $this->commandBus->handle(new AddEnvironment($project, $name, $environment));

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
            ->addEnvironmentOptions();
    }
}