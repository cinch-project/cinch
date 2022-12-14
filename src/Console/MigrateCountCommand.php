<?php

namespace Cinch\Console;

use Cinch\Command\Migrate\Migrate;
use Cinch\Command\Migrate\MigrateOptions;
use Cinch\Common\Author;
use Cinch\Component\Assert\Assert;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate:count', 'Migrates the next count migrations')]
class MigrateCountCommand extends AbstractCommand
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
        $count = (int) Assert::digit($input->getArgument('count'), 'count argument');

        $this->commandBus->handle(new Migrate(
            $project,
            new DeploymentTag($input->getArgument('tag')),
            new Author($input->getOption('deployer') ?: get_system_user()),
            new MigrateOptions($count),
            $this->getEnvironmentName($project)
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addArgument('number', InputArgument::REQUIRED, 'The number of eligible migrations to migrate')
            ->addOptionByName('deployer')
            ->addOptionByName('tag')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This will deploy the next <info><number></info> eligible migrations. They are selected based on the sorting 
policy of the migration store's directory configuration. 

<code-comment># limit to the first 4 eligible migrations</code-comment>
<code>cinch migrate project-name 4 --tag=hotfix-72631</code>
HELP
            );
    }
}