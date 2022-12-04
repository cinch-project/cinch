<?php

namespace Cinch\Console;

use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate-count', 'Migrates target database by count migration scripts')]
class MigrateCountCommand extends AbstractCommand
{
    public function __construct(
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('Migrate database to the latest version.')
            ->addProjectArgument()
            ->addTagOption()
            ->addEnvironmentNameOption();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);
        $env = $project->getEnvironmentMap()->get($this->getEnvironmentName($project));
        dump($env);
        return self::SUCCESS;
    }
}