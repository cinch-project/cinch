<?php

namespace Cinch\Console\Command;

use Cinch\Command\RollbackBy;
use Cinch\Common\Author;
use Cinch\Console\Command;
use Cinch\History\DeploymentTag;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rollback:count', 'Rollback a specific number of changes')]
class RollbackCount extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $input->getArgument('count');
        $this->executeCommand(new \Cinch\Command\Rollback(
            $this->projectId,
            new Author($input->getOption('deployer') ?: system_user()),
            new DeploymentTag($input->getOption('tag')),
            RollbackBy::count($count),
            $this->envName
        ), "rolling back $count changes");

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('count', InputArgument::REQUIRED, 'Number of migrations to rollback')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
The <info><count></info> argument only includes changes that can be rolled back: 'once' migrations with 
a status of 'migrated'. The first <info><count></info> changes (descending) matching this state, will 
be rolled back. Note: the changes rolled back can belong to more than one deployment.
 
<code-comment># rollback 5 changes, also specify a deployment tag</>
<code>cinch rollback project-name 5 --tag=hello</>
HELP
            );
    }
}