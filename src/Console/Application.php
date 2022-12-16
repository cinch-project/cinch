<?php

namespace Cinch\Console;

use Cinch\Console\Commands\ConsoleCommand;
use Exception;
use League\Tactician\CommandBus;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Path;

class Application extends SymfonyApplication
{
    private Container $container;

    public function __construct(string $version)
    {
        parent::__construct('cinch', $version);
    }

    /**
     * @throws \Throwable
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        /* compile container, which requires the project argument and working-dir option to compose the
         * projectDir parameter. Bind command input definition to a temp Input and reset it afterwards.
         */
        if ($command instanceof ConsoleCommand) {
            $saveDefinition = new InputDefinition();
            $saveDefinition->setArguments($command->getDefinition()->getArguments());
            $saveDefinition->setOptions($command->getDefinition()->getOptions());

            /* merge and bind definition to a temp Input */
            $command->mergeApplicationDefinition();
            $tmpInput = new ArgvInput(definition: $command->getDefinition());
            $command->setDefinition($saveDefinition); // reset

            /* extract input values and compile container */
            $project = $tmpInput->getArgument('project');
            $workingDir = $tmpInput->getOption('working-dir') ?: getcwd();
            $projectDir = Path::makeAbsolute(Path::join($workingDir, $project), getcwd());
            $this->container = self::compileContainer($projectDir);

            /* inject values into command */
            $command->setProjectDir($projectDir);
            $command->setCommandBus($this->container->get(CommandBus::class));
        }

        return parent::doRunCommand($command, $input, $output);
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

    /**
     * @throws Exception
     */
    private static function compileContainer(string $projectDir): Container
    {
        $rootDir = dirname(__DIR__, 2);
        $resourceDir = "$rootDir/resources";

        $container = new ContainerBuilder();
        $container->setParameter('cinch.version', getenv('CINCH_VERSION'));
        $container->setParameter('cinch.resource_dir', $resourceDir);
        $container->setParameter('schema.version', getenv('CINCH_SCHEMA_VERSION'));
        $container->setParameter('schema.description', getenv('CINCH_SCHEMA_DESCRIPTION'));
        $container->setParameter('schema.release_date', getenv('CINCH_SCHEMA_RELEASE_DATE'));
        $container->setParameter('twig.auto_reload', getenv('CINCH_ENV') != 'prod');
        $container->setParameter('twig.debug', getenv('CINCH_DEBUG') === '1');
        $container->setParameter('twig.template_dir', $resourceDir);
        $container->setParameter('project.dir', $projectDir);

        $loader = new YamlFileLoader($container, new FileLocator("$rootDir/config"));
        $loader->load('services.yml');
        $container->compile();

        return $container;
    }
}