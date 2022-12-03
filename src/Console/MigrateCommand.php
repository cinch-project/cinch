<?php

namespace Cinch\Console;

use Cinch\Project\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate', 'Migrate target database to the latest version')]
class MigrateCommand extends AbstractCommand
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
            ->addTagOption();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);
        $environment = $project->getEnvironmentMap()->get($this->environmentName);

        dump($environment);

        // cinch add-env sales <name> <target>
        // cinch migrate sales --tag=x --dry-run
        // cinch migrate-count sales [num=1] --tag=x
        // cinch migrate-file sales <file> --tag=x

        // cinch tag sales <name>
        // cinch tag-list sales --from
        // cinch tag-clear sales <name>

        // cinch rollback sales [tag=latest]
        // cinch rollback-count sales [num=1]
        // cinch rollback-date sales <datetime>
        // cinch rollback-change sales <location> [location, ...]

        return self::SUCCESS;
    }
}