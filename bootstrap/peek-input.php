<?php

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Filesystem\Path;

return new class {
    public readonly string $projectDir;
    public readonly string $command;
    public readonly string $timeZone;
    public readonly string $envName;

    public function __construct()
    {
        $this->parse();
    }

    private function parse(): void
    {
        $argv = $_SERVER['argv'] ?? [];

        /* below validation shouldn't raise an error when requesting help. */
        if ($this->isRequestingHelp($argv)) {
            $this->timeZone = get_system_time_zone();
            $this->projectDir = getcwd();
            $this->envName = $this->command = '';
            return;
        }

        $projectName = null;
        $workingDir = null;
        $timeZone = null;
        $envName = '';

        for ($i = 2, $argc = count($argv); $i < $argc; $i++) {
            $arg = $argv[$i];

            if (!$workingDir && $this->hasOption($arg, '--working-dir', 'w'))
                $workingDir = $this->getOption($arg, '--working-dir', 'w', $argv, $i);
            else if (!$timeZone && $this->hasOption($arg, '--time-zone', 'z'))
                $timeZone = $this->getOption($arg, '--time-zone', 'z', $argv, $i);
            else if (!$envName && $this->hasOption($arg, '--environment', 'e'))
                $envName = $this->getOption($arg, '--environment', 'e', $argv, $i);
            else if (!$projectName && $arg[0] != '-')
                $projectName = $arg; /* first "argument" MUST be project name */

            if ($projectName && $workingDir && $timeZone && $envName)
                break;
        }

        if (!$projectName)
            throw new InvalidArgumentException('missing project argument');

        $workingDir = $workingDir ? Path::makeAbsolute($workingDir, getcwd()) : getcwd();
        $this->projectDir = Path::join($workingDir, $projectName);
        $this->command = $argv[1];
        $this->timeZone = $timeZone ?? get_system_time_zone();
        $this->envName = $envName;
    }

    private function getOption(string $arg, string $name, string $shortcut, array $argv, int &$i): string
    {
        $parts = explode('=', $arg, 2);
        $isShortcut = !str_starts_with($arg, '--');

        if ($isShortcut && !str_ends_with($parts[0], $shortcut))
            throw new InvalidOptionException("-$shortcut missing value");

        /* "option=value" format */
        if (count($parts) == 2)
            return $parts[1];

        /* "option value" format */
        if ($i + 1 >= count($argv))
            throw new InvalidOptionException("$name missing value");

        return $argv[++$i];
    }

    private function hasOption(string $arg, string $name, string $shortcut): bool
    {
        return str_starts_with($arg, $name) || $this->hasShortcut($arg, $shortcut);
    }

    private function hasShortcut(string $arg, string $shortcut): bool
    {
        if ($arg && $arg[0] == '-' && strlen($arg) >= 2 && $arg[1] != '-') {
            $parts = explode('=', $arg, 2);
            if (str_contains($parts[0], $shortcut))
                return true;
        }

        return false;
    }

    private function isRequestingHelp(array $argv): bool
    {
        $count = count($argv);

        /* first condition is running cinch with no arguments `cinch`, which runs symfony "list" */
        if ($count < 2 || $argv[1] == 'help' || $argv[1] == 'list')
            return true;

        for ($i = 1; $i < $count; $i++)
            if ($argv[$i] == '--help' || $this->hasShortcut($argv[$i], 'h'))
                return true;

        return false;
    }
};