<?php

namespace Cinch\Console\Command;

use Cinch\Command\RollbackBy;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rollback:count', 'Roll back a specific number of changes')]
class RollbackCount extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $input->getArgument('count');
        $this->executeRollback($input, RollbackBy::count($count), "rolling back $count changes");
        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('count', InputArgument::REQUIRED, 'Number of changes to roll back')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('dry-run')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
The <info><count></info> argument only includes changes that can be rolled back: 'once' migrations with a status of 
'migrated'. The first <info><count></info> changes (descending) matching this state, will be rolled back.

<comment>Example History:</> 
path   tag    migrate_policy    status         deployed_at
----------------------------------------------------------
a.sql  tag-1  always-after      migrated       02:00
a.sql  tag-2  always-after      remigrated     04:00
<bg=#666>b.sql  tag-3  once              migrated       09:00</>
c.sql  tag-4  onchange-before   migrated       13:00
<bg=#666>d.sql  tag-4  once              migrated       13:01</>
e.sql  tag-5  once              migrated       15:00 
e.sql  tag-6  once              rollbacked     20:00

<code-comment># request to roll back the last 3 changes</>
<code>cinch rollback:count project 3 --tag=hello</>

Using the above example history, cinch will only roll back d.sql and then b.sql, even though a count of 3 
was requested. The most recent change for e.sql is rollbacked, thus it is excluded. Note: d.sql and b.sql 
belong to different deployments (tag-4 and tag-3 respectively).
HELP
            );
    }
}