<?php

namespace Cinch\Console\Commands;

use Cinch\Component\Assert\Assert;
use Cinch\Project\Project;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use League\Tactician\CommandBus;
use Monolog\Handler\NoopHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Path;

abstract class ConsoleCommand extends Command implements SignalableCommandInterface
{
    /* lookup table for commonly used options: see getOptionByName() */
    private const OPTIONS = [
        'env' => [null, InputOption::VALUE_REQUIRED, 'Sets the environment [default: project:environments.default]'],
        'tag' => [null, InputOption::VALUE_REQUIRED, 'Deployment tag [default: version 7 UUID]'],
        'deployer' => [null, InputOption::VALUE_REQUIRED, 'User or application performing deployment [default: current user]'],
        'migration-store' => ['m', InputOption::VALUE_REQUIRED, 'Migration Store DSN', '.'],
    ];

    protected readonly LoggerInterface $logger;
    protected readonly ProjectId $projectId;
    protected readonly string $envName;
    private readonly Container $container;

    public function mergeApplicationDefinition(bool $mergeArgs = true): void
    {
        /* sneak project argument in, mandatory for every "cinch" command */
        $args = $this->getDefinition()->getArguments();
        array_unshift($args, new InputArgument('project', InputArgument::REQUIRED, 'Project name'));
        $this->getDefinition()->setArguments($args);
        parent::mergeApplicationDefinition($mergeArgs);
    }

    /**
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $project = $input->getArgument('project');
        $workingDir = $input->getOption('working-dir') ?: getcwd();
        $projectDir = Path::makeAbsolute(Path::join($workingDir, $project), getcwd());
        $this->projectId = new ProjectId($projectDir);
        $this->container = self::loadContainer($projectDir);
        $this->envName = $input->hasOption('env') ? ($input->getOption('env') ?? '') : '';
        $this->logger = new Logger($project, [new NoopHandler()]);
    }

    /**
     * @throws Exception
     */
    protected function dispatch(object $command): void
    {
        $this->container->get(CommandBus::class)->handle($command);
    }

    /**
     * @throws Exception
     */
    protected function getProject(): Project
    {
        return $this->container->get(ProjectRepository::class)->get($this->projectId);
    }

    protected function addOptionByName(string $name, string $description = ''): static
    {
        $args = self::OPTIONS[$name] ?? null;

        if (!$args)
            throw new RuntimeException("option '$name' does not exist");

        if ($description)
            $args[2] = $description;

        return $this->addOption($name, ...$args);
    }

    /**
     * @throws Exception
     */
    protected function parseDateValue(string $value): DateTimeInterface|null
    {
        if (preg_match('~([+\-]\d\d:?\d\d)$~', $value, $m)) {
            $timeZone = new DateTimeZone($m[1]);
            $value = substr($value, 0, -strlen($m[1]));
        }
        else {
            $timeZone = new DateTimeZone(get_system_time_zone());
        }

        $date = str_contains($value, '-');
        $time = str_contains($value, ':');

        if ($date && $time)
            $format = 'Y-m-d\TH:i:s';
        else if ($date)
            $format = 'Y-m-d';
        else if ($time)
            $format = 'H:i:s';
        else
            $format = '';

        if ($format) {
            Assert::date($value, $format, '(date) value argument');
            return new DateTimeImmutable($value, $timeZone);
        }

        return null;
    }

    protected function getIntOption(InputInterface $input, string $name, int $default = 0): int
    {
        if (($value = $input->getOption($name)) === false)
            return $default;
        return is_int($value) ? $value : Assert::digit($value, $name);
    }

    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal): never
    {
        $name = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            default => 'UNKNOWN' // never happen
        };

        echo "'{$this->getName()}' command interrupted by {$name}[$signal]\n";
        exit(0);
    }

    /**
     * @throws Exception
     */
    private static function loadContainer(string $projectDir): Container
    {
        if (isset($container))
            return $container;

        $rootDir = dirname(__DIR__, 3);
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