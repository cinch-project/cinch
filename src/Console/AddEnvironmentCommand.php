<?php

namespace Cinch\Console;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Project\ProjectRepository;
use Cinch\Services\AddEnvironmentService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('add-env', 'Adds a new environment to a project')]
class AddEnvironmentCommand extends AbstractCommand
{
    public function __construct(
        private readonly AddEnvironmentService $addEnvService,
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('This does cool stuff')
            ->addProjectArgument()
            ->addArgument('name', InputArgument::REQUIRED, 'Environment name')
            ->addArgument('target', InputArgument::REQUIRED, 'Target (database) DSN')
            ->addOption('history', 'H', InputOption::VALUE_REQUIRED,
                'History (database) DSN [default: target]')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED,
                "Schema name to use for history tables [default: cinch_{projectName}]")
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED,
                "History table name prefix", '')
            ->addOption('deploy-lock-timeout', null, InputOption::VALUE_REQUIRED,
                "Seconds to wait for a deploy lock before timing out the request",
                Environment::DEFAULT_DEPLOY_LOCK_TIMEOUT)
            ->addOption('auto-create-schema', 'a', InputOption::VALUE_REQUIRED,
                "Automatically create the history schema if it doesn't exist",
                Environment::DEFAULT_AUTO_CREATE_SCHEMA);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->projectRepository->get($this->projectId);
        $name = $input->getArgument('name');
        $environment = $this->getEnvironmentFromInput($input, $project->getName());

        $this->logger->info("adding environment $name to project {$project->getName()}");
        $this->addEnvService->execute($project, $name, $environment);

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }
}