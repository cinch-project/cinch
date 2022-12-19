<?php

namespace Cinch\Console\Command;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
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
            ->addOption('history', 'H', InputOption::VALUE_REQUIRED, 'History database DSN [default: target]')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'History schema name [default: cinch_$projectName]')
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED, "History table name prefix", '')
            ->addOption('deploy-timeout', null, InputOption::VALUE_REQUIRED, 'Timeout seconds for a deploy lock', Environment::DEFAULT_DEPLOY_TIMEOUT)
            ->addOption('create-schema', 'a', InputOption::VALUE_REQUIRED, 'Create history schema if it does not exist', Environment::DEFAULT_CREATE_SCHEMA);
    }

    protected function getEnvironmentFromInput(InputInterface $input): Environment
    {
        $target = $input->getArgument('target');
        $history = $input->getOption('history') ?: $target;
        $defaultSchema = sprintf(Environment::DEFAULT_SCHEMA_FORMAT, $input->getArgument('project'));

        return new Environment(
            new Dsn($target),
            new Dsn($history),
            $input->getOption('schema') ?: $defaultSchema,
            $input->getOption('table-prefix'),
            $this->getIntOption($input, 'deploy-timeout'),
            $input->getOption('create-schema')
        );
    }
}