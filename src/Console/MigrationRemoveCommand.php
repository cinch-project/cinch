<?php

namespace Cinch\Console;

use Cinch\Command\RemoveMigrationCommand;
use Cinch\Common\Location;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:remove', 'Removes a migration')]
class MigrationRemoveCommand extends AbstractCommand
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

        $this->commandBus->handle(new RemoveMigrationCommand(
            $project->getMigrationStoreDsn(),
            $project->getEnvironmentMap()->get($this->getEnvironmentName($project)),
            new Location($input->getArgument('location'))
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        // cinch migration:remove <project> <location>
        $this
            ->addProjectArgument()
            ->addArgument('location', InputArgument::REQUIRED, 'Migration location (relative to migration store)')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This command cannot remove migrations that have already been deployed. For migrations with an 'always' 
or 'onchange' migrate policy, update their policy to 'never'. 
HELP
            );
    }
}