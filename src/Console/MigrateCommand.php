<?php

namespace Cinch\Console;

use Cinch\Common\Author;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate', 'Migrates target database to the latest version')]
class MigrateCommand extends AbstractCommand
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('Migrate database to the latest version.')
            ->addProjectArgument()
            ->addOption('deployer', null, InputOption::VALUE_REQUIRED,
                'The user or application performing the migration [default: current system user]')
            ->addTagOption()
            ->addEnvironmentNameOption();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);

        $command = new \Cinch\Command\MigrateCommand(
            $project,
            new Author($input->getOption('deployer') ?: get_system_user()),
            $input->getOption('tag'),
            $this->getEnvironmentName($project)
        );

        $this->commandBus->handle($command);

        // cinch create <project> <target>
        // cinch add <project> <location> [options] --author=s --policy=always --description=''
        // cinch check <project> -- hooks, env, store, inconsistencies between migration store and history
        // cinch repair <project> --checksums, etc.

        // cinch env <project> <name> <target>
        // cinch env-list <project>
        // cinch env-delete <project> <name>

        // cinch migrate <project> --tag=x --dry-run
        // cinch migrate-count <project> [num=1] --tag=x
        // cinch migrate-script <project> <file> --tag=x

        // cinch tag <project> <name>
        // cinch tag-list <project> --from
        // cinch tag-clear <project> <name>

        // cinch rollback <project> [tag=latest]
        // cinch rollback-count <project> [num=1]
        // cinch rollback-date <project> <datetime>
        // cinch rollback-script <project> <location> [location, ...]

        // cinch history - display deployed changes
        // cinch pending - display changes that have not been deployed

        return self::SUCCESS;
    }
}