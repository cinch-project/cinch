<?php

namespace Cinch\Console\Commands;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('env', 'Lists all environments')]
class Env extends ConsoleCommand
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $map = $this->getProject()->getEnvironmentMap();
        $default = $map->getDefaultName();

        foreach ($map->all() as $name => $env) {
            $createSchema = $env->createSchema ? 'true' : 'false';
            $output->writeln([
                "<info>$name" . ($name == $default ? ' (default)' : '') . "</info>",
                "  deploy_timeout $env->deployTimeout",
                "  target '$env->targetDsn'",
                "  history '$env->historyDsn'",
                "    - schema '$env->schema', table_prefix '$env->tablePrefix', create_schema $createSchema"
            ]);
        }

        return self::SUCCESS;
    }

    public function handleSignal(int $signal): never
    {
        echo "delete project\n";
        parent::handleSignal($signal);
    }
}