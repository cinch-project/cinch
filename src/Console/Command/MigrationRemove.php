<?php

namespace Cinch\Console\Command;

use Cinch\Command\Migration\RemoveMigration;
use Cinch\Common\StorePath;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:remove', 'Removes a migration')]
class MigrationRemove extends ConsoleCommand
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = new StorePath($input->getArgument('path'));
        $this->executeCommand(new RemoveMigration($this->projectId, $this->envName, $path));
        return self::SUCCESS;
    }

    protected function configure()
    {
        // cinch migration:remove <project> <path>
        $this
            ->addProjectArgument()
            ->addArgument('path', InputArgument::REQUIRED, 'Migration store path (relative to store root)')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This command cannot remove migrations that have already been deployed. For migrations with an 'always' 
or 'onchange' migrate policy, update their policy to 'never'. 
HELP
            );
    }
}