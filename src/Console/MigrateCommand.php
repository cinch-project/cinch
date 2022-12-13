<?php

namespace Cinch\Console;

use Cinch\Command\MigrateOptions;
use Cinch\Common\Author;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate', 'Migrates all eligible migrations')]
class MigrateCommand extends AbstractCommand
{
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
        $this->commandBus->handle(new \Cinch\Command\MigrateCommand(
            $project,
            new DeploymentTag($input->getArgument('tag')),
            new Author($input->getOption('deployer') ?: get_system_user()),
            new MigrateOptions(),
            $this->getEnvironmentName($project)
        ));

        // cinch create <project> <target>

        // old
        // cinch env <project> <name> <target>
        // cinch env-list <project>
        // cinch env-delete <project> <name>

        // cinch migration <project> --all, -a (default is eligible only)
        // cinch migration-add <project> <location> <description> --author=s --migrate-policy=always
        // cinch migration-remove <project> <location>

        // cinch env <project> (listing)
        // cinch env-add <project> <name> <target>
        // cinch env-remove <project> <name>

        // cinch tag <project> (listing)
        // cinch migrate <project> [<count|locations...>] --dry-run --tag
        // cinch rollback <project> <tag|datetime|count|location...> --tag

        // cinch rollback:script rollback:date rollback:count <project> <count> rollback <tag>
        // cinch check <project> -- hooks, env, store, inconsistencies between migration store and history
        // cinch repair <project> --checksums, etc.
        // cinch history - display deployed changes

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this->addProjectArgument()
            ->addOption('deployer', null, InputOption::VALUE_REQUIRED,
                'The user or application [default: current system user]')
            ->addTagOption()
            ->addEnvironmentNameOption()
            ->setHelp(<<<HELP
<code-comment># migrate all eligible migrations</code-comment>
<code>cinch migrate project-name --tag=v12.9.3</code>
HELP
            );
    }
}