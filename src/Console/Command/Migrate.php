<?php

namespace Cinch\Console\Command;

use Cinch\Command\MigrateOptions;
use Cinch\Common\Author;
use Cinch\Console\Command;
use Cinch\History\DeploymentTag;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate', 'Migrate all eligible migrations')]
class Migrate extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executeCommand(new \Cinch\Command\Migrate(
            $this->projectId,
            new DeploymentTag($input->getOption('tag')),
            new Author($input->getOption('deployer') ?: system_user()),
            new MigrateOptions(),
            $this->envName
        ), 'Migrating all eligible migrations');

        // cinch create <project> <target>

        // old
        // cinch env <project> <name> <target>
        // cinch env-list <project>
        // cinch env-delete <project> <name>

        // cinch migration <project> --all, -a (default is eligible only)
        // cinch migration-add <project> <path> <description> --author=s --migrate-policy=always
        // cinch migration-remove <project> <path>

        // cinch env <project> (listing)
        // cinch env-add <project> <name> <target>
        // cinch env-remove <project> <name>

        // cinch tag <project> (listing)
        // cinch migrate <project> [<count|paths...>] --dry-run --tag
        // cinch rollback <project> <tag|datetime|count|path...> --tag

        // cinch rollback:script rollback:date rollback:count <project> <count> rollback <tag>
        // cinch check <project> -- hooks, env, store, inconsistencies between migration store and history
        // cinch repair <project> --checksums, etc.
        // cinch history - display deployed changes

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
<code-comment># migrate all eligible migrations</>
<code>cinch migrate project-name --tag=v12.9.3</>
HELP
            );
    }
}