<?php

namespace Cinch\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    public function __construct(string $version)
    {
        parent::__construct('cinch', $version);
    }

    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        $output->getFormatter()->setStyle('code-comment', new OutputFormatterStyle('gray'));
        $output->getFormatter()->setStyle('code', new OutputFormatterStyle('blue'));
        parent::configureIO($input, $output);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            new InputOption('working-dir', 'w', InputOption::VALUE_REQUIRED, 'Sets the working directory [default: pwd]'),
            new InputOption('time-zone', 'z', InputOption::VALUE_REQUIRED, 'Sets the time zone for logging and display [default: system]'),
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Performs all actions and logging without executing [default: off]'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display help for the given command'),
            new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Do not output any message'),
            new InputOption('version', 'V', InputOption::VALUE_NONE, 'Display this application version')
        ]);
    }

    protected function getDefaultCommands(): array
    {
        return [
            new HelpCommand(), // symfony
            new ListCommand(), // symfony
            new Commands\Create(),
            new Commands\Env(),
            new Commands\EnvAdd(),
            new Commands\EnvRemove(),
            new Commands\Migrate(),
            new Commands\MigrateCount(),
            new Commands\MigratePaths(),
            new Commands\MigrationAdd(),
            new Commands\MigrationRemove()
        ];
    }
}