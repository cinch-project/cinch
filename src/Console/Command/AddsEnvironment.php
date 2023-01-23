<?php

namespace Cinch\Console\Command;

use Cinch\Common\Environment;
use Cinch\Database\DatabaseDsn;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait AddsEnvironment
{
    protected function addTargetArgument(): static
    {
        return $this->addArgument('target', InputArgument::REQUIRED, 'Target database DSN');
    }

    protected function addEnvironmentOptions(): static
    {
        return $this
            ->addOption('history', 'H', InputOption::VALUE_REQUIRED, 'History database DSN <comment>[default: $target]</>')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'History schema name <comment>[default: "cinch_$project"]</>')
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED, "History table name prefix", '')
            ->addOption('deploy-timeout', null, InputOption::VALUE_REQUIRED, 'Timeout seconds for a deploy lock', Environment::DEFAULT_DEPLOY_TIMEOUT)
            ->addOption('auto-create', 'a', InputOption::VALUE_REQUIRED, 'Auto-create history schema if it does not exist', Environment::DEFAULT_AUTO_CREATE);
    }

    protected function getEnvironmentFromInput(InputInterface $input): Environment
    {
        $target = $input->getArgument('target');
        $history = $input->getOption('history') ?: $target;
        $defaultSchema = sprintf(Environment::DEFAULT_SCHEMA_FORMAT, $input->getArgument('project'));

        return new Environment(
            new DatabaseDsn($target),
            new DatabaseDsn($history),
            $input->getOption('schema') ?: $defaultSchema,
            $input->getOption('table-prefix'),
            $this->getIntOption($input, 'deploy-timeout'),
            $input->getOption('auto-create')
        );
    }
}