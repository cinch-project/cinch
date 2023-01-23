<?php

namespace Cinch\Console;

use Cinch\Common\Description;
use Cinch\Common\Environment;
use Cinch\Component\Assert\Assert;
use Cinch\Database\DatabaseDsn;
use Cinch\Hook;
use Cinch\LastErrorException;
use Cinch\MigrationStore\StoreDsn;
use Cinch\Project\EnvironmentMap;
use Cinch\Project\Project;
use Cinch\Project\ProjectName;
use Cinch\Project\ProjectRepository;
use DateTimeZone;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class YamlFileProjectRepository implements ProjectRepository
{
    private readonly string $projectDir;

    public function __construct(private readonly string $projectFile)
    {
        $this->projectDir = dirname($this->projectFile);
    }

    /**
     * @throws Exception
     */
    public function get(ProjectName $name): Project
    {
        $this->assertProjectName($name);

        if (!file_exists($this->projectFile))
            throw new Exception("project '$this->projectDir' does not exist");

        $state = Yaml::parseFile($this->projectFile, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);

        /* make sure project file has same name as $name argument */
        $stateName = new ProjectName(Assert::stringProp($state, 'name', 'project name'));
        Assert::equals($stateName->value, $name->value, 'project name');

        return new Project(
            $name,
            new Description(Assert::stringProp($state, 'description', 'project description')),
            new DateTimeZone(Assert::stringProp($state, 'time_zone', 'project time zone')),
            new StoreDsn(Assert::objectProp($state, 'migration_store', "migration_store")),
            $this->createEnvironmentMap($state, $name),
            $this->createHooks($state),
            Assert::ifPropSet($state, 'single_transaction', true, 'single_transaction')->bool()->value()
        );
    }

    /**
     * @throws Exception
     */
    public function add(Project $project): void
    {
        $this->assertProjectName($project->getName());

        if (file_exists($this->projectDir))
            throw new Exception("project '$this->projectDir' already exists");

        /* create project directory */
        (new Filesystem())->mkdir($this->projectDir);

        try {
            $this->update($project);
        }
        catch (Exception $e) {
            silent_call($this->remove(...), $this->projectDir);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function update(Project $project): void
    {
        static $flags = Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;

        $this->assertProjectName($project->getName());

        $state = Yaml::dump($project->snapshot(), 100, flags: $flags);
        if (@file_put_contents($this->projectFile, $state) === false)
            throw new LastErrorException();
    }

    public function remove(ProjectName $name): void
    {
        $this->assertProjectName($name);
        (new Filesystem())->remove($this->projectFile);
    }

    private function assertProjectName(ProjectName $name): void
    {
        Assert::equals(basename($this->projectDir), $name->value, 'project name');
    }

    /**
     * @throws Exception
     */
    private function createEnvironmentMap(object $state, ProjectName $projectName): EnvironmentMap
    {
        $default = '';
        $environments = [];

        foreach (Assert::objectProp($state, 'environments', 'environments') as $name => $value) {
            $path = "environments.$name";
            if ($name == 'default')
                $default = Assert::that($value, $path)->string()->notEmpty()->value();
            else
                $environments[$name] = $this->createEnvironment($value, $projectName, $path);
        }

        if (!$default)
            throw new Exception("missing 'default' environment key");

        return new EnvironmentMap($default, $environments);
    }

    /**
     * @throws Exception
     */
    private function createEnvironment(mixed $value, ProjectName $projectName, string $path): Environment
    {
        $tablePrefix = '';
        $schema = sprintf(Environment::DEFAULT_SCHEMA_FORMAT, $projectName);
        $autoCreate = Environment::DEFAULT_AUTO_CREATE;

        $target = Assert::thatProp($value, 'target', "$path.target")->object()->value();
        $history = Assert::ifProp($value, 'history', $target, "$path.history")->object()->value();
        $deployTimeout = Assert::ifProp($value, 'deploy_timeout', Environment::DEFAULT_DEPLOY_TIMEOUT, "$path.deploy_timeout")->int()->greaterThanEqualTo(0)->value();

        if (property_exists($history, 'schema') && is_object($history->schema)) {
            $s = $history->schema;
            $path = "$path.history.schema";
            $schema = Assert::ifPropSet($s, 'name', $schema, "$path.name")->string()->value();
            $tablePrefix = Assert::ifPropSet($s, 'table_prefix', $tablePrefix, "$path.table_prefix")->string()->value();
            $autoCreate = Assert::ifPropSet($s, 'auto_create', $autoCreate, "$path.auto_create")->bool()->value();

            unset($history->schema);

            /* if history is empty after deleting 'schema' property, make a verbatim copy of target */
            if (!($history = arrayify($history)))
                $history = $target;
        }

        return new Environment(new DatabaseDsn($target), new DatabaseDsn($history), $schema, $tablePrefix, $deployTimeout, $autoCreate);
    }

    /**
     * @throws Exception
     */
    private function createHooks(object $state): array
    {
        $hooks = [];
        $rawHooks = Assert::ifProp($state, 'hooks', [], 'hooks')->array()->value();

        foreach ($rawHooks as $i => $hook) {
            $path = "hooks[$i]";

            $events = Assert::prop($hook, 'events', "$path.events");
            if (is_string($events))
                $events = [$events];

            $hooks[] = new Hook\Hook(
                new Hook\Action(Assert::stringProp($hook, 'action', "$path.action"), $this->projectDir),
                array_map(fn($e) => Hook\Event::from($e), Assert::that($events, 'events')->array()->notEmpty()->value()),
                Assert::ifProp($hook, 'timeout', Hook\Hook::DEFAULT_TIMEOUT, "$path.timeout")->int()->value(),
                Assert::ifProp($hook, 'abort_on_error', true, "$path.abort_on_error")->bool()->value(),
                Assert::ifProp($hook, 'arguments', [], "$path.arguments")->array()->value(),
                (array) Assert::ifProp($hook, 'headers', (object) [], "$path.arguments")->object()->value()
            );
        }

        return $hooks;
    }
}