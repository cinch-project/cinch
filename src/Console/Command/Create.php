<?php

namespace Cinch\Console\Command;

use Cinch\Command\CreateProject;
use Cinch\Common\Dsn;
use Cinch\Project\EnvironmentMap;
use Cinch\Project\Project;
use Cinch\Project\ProjectName;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('create', 'Create a project')]
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

        $this->executeCommand(new CreateProject($project, $envName), "Creating project '$projectName'");

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
            ->addTargetArgument()
            ->addEnvironmentOptions()
            ->addOptionByName('migration-store')
            ->addOptionByName('env', 'Sets the project\'s default environment [default: $projectName]');
    }
}