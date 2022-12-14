<?php

namespace Cinch\Console\Commands;

use Cinch\Command\Project\CreateProject;
use Cinch\Common\Dsn;
use Cinch\Project\EnvironmentMap;
use Cinch\Project\Project;
use Cinch\Project\ProjectName;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand('create', 'Creates a project')]
class Create extends AbstractCommand
{
    use ConfiguresEnvironment;

    public function __construct(private readonly string $tempLogFile)
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = new ProjectName($input->getArgument('project'));
        $environment = $this->getEnvironmentFromInput($input, $projectName);
        $envName = $this->environmentName ?: $projectName->value;

        $project = new Project(
            $this->projectId,
            $projectName,
            new Dsn($input->getOption('migration-store')),
            new EnvironmentMap($envName, [$envName => $environment])
        );

        $this->logger->info("creating project");
        $this->commandBus->handle(new CreateProject($project, $envName));

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

    protected function configure()
    {
        $this
            ->addProjectArgument()
            ->addEnvironmentOptions()
            ->addOptionByName('migration-store')
            ->addOptionByName('env', 'Sets the project\'s default environment [default: $projectName]');
    }
}