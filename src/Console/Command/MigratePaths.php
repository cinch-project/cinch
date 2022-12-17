<?php

namespace Cinch\Console\Command;

use Cinch\Command\Migrate\MigrateOptions;
use Cinch\Common\Author;
use Cinch\Common\StorePath;
use Cinch\History\DeploymentTag;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate:paths', 'Migrates one or more migration store paths')]
class MigratePaths extends ConsoleCommand
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = array_map(fn(string $p) => new StorePath($p), $input->getArgument('paths'));

        $this->executeCommand(new \Cinch\Command\Migrate\Migrate(
            $this->projectId,
            new DeploymentTag($input->getArgument('tag')),
            new Author($input->getOption('deployer') ?: get_system_user()),
            new MigrateOptions($paths),
            $this->envName
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'One or more migration store paths')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
The <info><paths></> are deployed in the order they are specified.

<code-comment># deploy 2 migrations in the given order</>
<code>cinch migrate project-name 2022/create-pricing.php 2022/drop-old-pricing.sql --tag=pricing-2.0</>
HELP
            );
    }
}