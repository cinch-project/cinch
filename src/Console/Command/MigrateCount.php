<?php

namespace Cinch\Console\Command;

use Cinch\Command\MigrateOptions;
use Cinch\Component\Assert\Assert;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate:count', 'Migrates the next count migration scripts')]
class MigrateCount extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $num = (int) Assert::digit($input->getArgument('number'), 'number argument');
        $this->executeMigrate($input, new MigrateOptions($num), "Migrating the next $num migration(s)");
        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('number', InputArgument::OPTIONAL, 'The number of eligible migrations to migrate', 1)
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('dry-run')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This will deploy the next <info><number></> eligible migrations. They are selected based on the sorting 
policy of the migration store's directory configuration. 

<code-comment># limit to the first 4 eligible migrations</>
<code>cinch migrate project 4 --tag=hotfix-72631</>
HELP
            );
    }
}
