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

#[AsCommand('create', 'Creates a project')]
class Create extends ConsoleCommand
{
    use AddsEnvironment;

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = $input->getArgument('project');
        $environment = $this->getEnvironmentFromInput($input);
        $envName = $this->envName ?: $projectName;

        $project = new Project(
            $this->projectId,
            new ProjectName($projectName),
            new Dsn($input->getOption('migration-store')),
            new EnvironmentMap($envName, [$envName => $environment])
        );

        $this->logger->info("creating project");
        $this->dispatch(new CreateProject($project, $envName));

        /* move temp log to project log dir, now that project dir exists */
        //$logFile = Path::join($this->projectId, 'log', basename($this->tempLogFile));
        //(new Filesystem())->rename($this->tempLogFile, $logFile);

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
            ->addTargetArgument()
            ->addEnvironmentOptions()
            ->addOptionByName('migration-store')
            ->addOptionByName('env', 'Sets the project\'s default environment [default: $projectName]');
    }
}