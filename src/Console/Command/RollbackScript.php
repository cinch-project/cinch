<?php

namespace Cinch\Console\Command;

use Cinch\Command\RollbackBy;
use Cinch\Common\StorePath;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rollback:script', 'Rolls back one or more migration scripts')]
class RollbackScript extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = [];
        $rawPaths = [];

        foreach ($input->getArgument('paths') as $p) {
            $paths[] = new StorePath($p);
            $rawPaths[] = $p;
        }

        $title = "Rolling back migration scripts " . implode(', ', $rawPaths);
        $this->executeRollback($input, RollbackBy::script($paths), $title);
        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'One or more migration store paths')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('dry-run')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
The given <info><paths></info> must be migrations scripts that have already been deployed.

<code-comment># roll back previously deployed migration scripts</>
<code>cinch rollback:script project 2022/09/a.sql 2022/10/b.sql</>
HELP
            );
    }
}
