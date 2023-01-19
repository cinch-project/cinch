<?php

namespace Cinch\Console\Command;

use Cinch\Command\RollbackBy;
use Cinch\Console\Command;
use Cinch\History\DeploymentTag;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rollback', 'Rolls back to previous deployment or optional tag')]
class Rollback extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($tag = $input->getArgument('tag')) !== null)
            $tag = new DeploymentTag($tag);

        $title = $tag ? "rolling back to deployment tag '$tag'" : 'rolling back to previous deployment';
        $this->executeRollback($input, RollbackBy::tag($tag), $title);
        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('tag', InputArgument::OPTIONAL, 'Rollback deployments since this tag')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('dry-run')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
When <info><tag></info> is not provided, the latest migrate deployment that has at least one change, is 
rolled back. When <info><tag></info> is provided, the latest migrate deployments that have at least one
change, and occurred since tag, are rolled back.
 
<code-comment># roll back TO the previous deployment (ie: roll back the latest deployment)</>
<code>cinch rollback project</>

<code-comment># roll back TO a deployment tag. if v1.2.3 is the latest deployment, 
# nothing is rolled back, since the target database is already at tag v1.2.3.</>
<code>cinch rollback project v1.2.3</>
HELP
            );
    }
}