<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Migration\RemoveMigration;
use Cinch\Common\StorePath;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migration:remove', 'Removes a migration')]
class MigrationRemove extends AbstractCommand
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

        $this->commandBus->handle(new RemoveMigration(
            $project->getMigrationStoreDsn(),
            $project->getEnvironmentMap()->get($this->getEnvironmentName($project)),
            new StorePath($input->getArgument('path'))
        ));

        return self::SUCCESS;
    }

    protected function configure()
    {
        // cinch migration:remove <project> <path>
        $this
            ->addProjectArgument()
            ->addArgument('path', InputArgument::REQUIRED, 'Migration store path (relative to migration store root)')
            ->addOptionByName('env')
            ->setHelp(<<<HELP
This command cannot remove migrations that have already been deployed. For migrations with an 'always' 
or 'onchange' migrate policy, update their policy to 'never'. 
HELP);
    }
}