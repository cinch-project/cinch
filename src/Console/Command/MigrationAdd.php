<?php

namespace Cinch\Console\Command;

use Cinch\Command\AddMigration;
use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Common\StorePath;
use Cinch\Console\Command;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:add', 'Add a migration')]
class MigrationAdd extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = new StorePath($input->getArgument('path'));
        $this->executeCommand(new AddMigration(
            $this->projectId,
            $path,
            MigratePolicy::from($input->getOption('migrate-policy')),
            new Author($input->getOption('author') ?: get_system_user()),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC')),
            new Description($input->getArgument('description')),
            new Labels($input->getOption('label'))
        ), "Adding migration script '$path'");

        return self::SUCCESS;
    }

    protected function configure()
    {
        $defaultPolicy = MigratePolicy::ONCE->value;
        $policies = "'" . implode("', '", array_map(fn($v) => $v->value, MigratePolicy::cases())) . "'";

        // cinch add <project> <path> <description> --author= --migrate-policy=
        $this
            ->addProjectArgument()
            ->addArgument('path', InputArgument::REQUIRED, 'Migration store path (relative to store root)')
            ->addArgument('description', InputArgument::REQUIRED, 'Migration description')
            ->addOption('migrate-policy', 'm', InputOption::VALUE_REQUIRED, "Migrate policy: $policies", $defaultPolicy)
            ->addOption('author', 'a', InputOption::VALUE_REQUIRED, 'Migration author [default: current system user]')
            ->addOption('label', 'l', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'One or more labels')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This command adds a skeleton migration to the migration store. You cannot add a pre-existing migration 
using this command. The <info><path></> must be relative and end with a .sql or .php extension. Directories 
will automatically be created. 

<code-comment># adds an 'onchange-after' migration</>
<code>cinch add project-name alter-user-table.sql "add phone column" --migrate-policy=onchange-after</>

<code-comment># adds a migration with two labels (migrate-policy set to default 'once')</>
<code>cinch add project-name 2022/05/alter-user-table.php "add phone column" -l label0 -l label1</>

After creation, the migration can be edited or removed. Once migrated, only 'onchange-*' and 'always-*'
migrations can be edited and no migration can ever be removed. To stop an 'always-*' migration from 
running, add it to it's directory <info>exclude</> list within the migration store config file. For 
'onchange-*', add it to the exclude list, like 'always=*' migrations, or simply stop changing the script.
HELP);
    }
}