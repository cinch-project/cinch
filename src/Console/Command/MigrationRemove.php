<?php

namespace Cinch\Console\Command;

use Cinch\Command\RemoveMigration;
use Cinch\Common\StorePath;
use Cinch\Console\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:remove', 'Remove a migration')]
class MigrationRemove extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = new StorePath($input->getArgument('path'));

        $this->executeCommand(
            new RemoveMigration($this->projectId, $this->envName, $path),
            "Removing migration script '$path'"
        );

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('path', InputArgument::REQUIRED, 'Migration store path (relative to store root)')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This command cannot remove migrations that have already been deployed. For migrations with an 'always' 
or 'onchange' migrate policy, add them to the directory exclude list within the migration store config file. 
HELP
            );
    }
}