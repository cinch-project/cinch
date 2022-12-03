<?php

namespace Cinch\Console;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Project\EnvironmentMap;
use Cinch\Project\Project;
use Cinch\Project\ProjectName;
use Cinch\Services\CreateProjectService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand('create', 'Creates a new project')]
class CreateProjectCommand extends AbstractCommand
{
    public function __construct(
        private readonly CreateProjectService $createProject,
        private readonly string $tempLogFile)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setHelp('This does cool stuff')
            ->addProjectArgument()
            ->addArgument('target', InputArgument::REQUIRED, 'Target (database) DSN')
            ->addOption('history', 'H', InputOption::VALUE_REQUIRED,
                'History (database) DSN [default: target]')
            ->addOption('migration-store', 'm', InputOption::VALUE_REQUIRED,
                "Migration Store DSN", '.')
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
        $projectName = new ProjectName($input->getArgument('project'));
        $environment = $this->getEnvironmentFromInput($input, $projectName);

        $project = new Project(
            $this->projectId,
            $projectName,
            new Dsn($input->getOption('migration-store')),
            new EnvironmentMap($projectName->value, [$projectName->value => $environment])
        );

        $this->logger->info("creating project");
        $this->createProject->execute($project, $this->environmentName);

        /* move temp log to project log dir, now that project dir exists */
        $logFile = Path::join($this->projectId, 'log', basename($this->tempLogFile));
        (new Filesystem())->rename($this->tempLogFile, $logFile);

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }
}