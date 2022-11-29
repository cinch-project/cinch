<?php

namespace Cinch\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Application extends SymfonyApplication
{
    public function __construct(string $version)
    {
        parent::__construct('cinch', $version);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            new InputOption('environment', 'e', InputOption::VALUE_REQUIRED, "Sets the environment [default: environments.default]"),
            new InputOption('working-dir', 'w', InputOption::VALUE_REQUIRED, 'Sets the working directory [default: pwd]'),
            new InputOption('time-zone', 'z', InputOption::VALUE_REQUIRED, 'Sets the time zone for logging and display [default: system]'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display help for the given command'),
            new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Do not output any message'),
            new InputOption('version', 'V', InputOption::VALUE_NONE, 'Display this application version'),
            new InputOption('ansi', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-ansi) ANSI output', null)
        ]);
    }

    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand(), new CompleteCommand()];
    }
}