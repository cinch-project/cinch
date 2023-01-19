<?php

namespace Cinch\Console\Command;

use Cinch\Console\Command;
use Cinch\Console\Query\GetProject;
use Cinch\Project\Project;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env', 'Lists all environments')]
class Env extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Project $project */
        $project = $this->executeQuery(new GetProject($this->projectId));
        $map = $project->getEnvironmentMap();
        $default = $map->getDefaultName();

        foreach ($map->all() as $name => $env) {
            $autoCreate = $env->autoCreate ? 'true' : 'false';
            $targetDsn = $env->targetDsn->toString(secure: false);
            $historyDsn = $env->historyDsn->toString(secure: false);
            $defstr = $name == $default ? '(default)' : '';

            $output->writeln([
                "<info>$name $defstr</>",
                "  deploy_timeout $env->deployTimeout",
                "  target '$targetDsn'",
                "  history '$historyDsn'",
                "  schema '$env->schema'",
                "  table_prefix '$env->tablePrefix'",
                "  auto_create $autoCreate"
            ]);
        }

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }

    public function configure()
    {
        $this->addProjectArgument()->setHelp('');
    }
}