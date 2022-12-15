<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Migrate\MigrateOptions;
use Cinch\Common\Author;
use Cinch\Common\StorePath;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate:paths', 'Migrates one or more migration store paths')]
class MigratePaths extends AbstractCommand
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);
        $paths = array_map(fn(string $l) => new StorePath($l), $input->getArgument('paths'));

        $this->commandBus->handle(new \Cinch\Command\Migrate\Migrate(
            $project,
            new DeploymentTag($input->getArgument('tag')),
            new Author($input->getOption('deployer') ?: get_system_user()),
            new MigrateOptions($paths),
            $this->getEnvironmentName($project)
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'One or more migration store paths')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
The <info><paths></info> are deployed in the order they are specified.

<code-comment># deploy 2 migrations in the given order</code-comment>
<code>cinch migrate project-name 2022/create-pricing.php 2022/drop-old-pricing.sql --tag=pricing-2.0</code>
HELP);
    }
}