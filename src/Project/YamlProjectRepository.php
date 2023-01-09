<?php

namespace Cinch\Project;

use Cinch\Common\Environment;
use Cinch\Component\Assert\Assert;
use Cinch\Database\DatabaseDsn;
use Cinch\Hook;
use Cinch\LastErrorException;
use Cinch\MigrationStore\StoreDsn;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class YamlProjectRepository implements ProjectRepository
{
    const DEFAULT_CONFIG_FILE = 'project.yml';

    public function __construct(private readonly string $configFile = self::DEFAULT_CONFIG_FILE)
    {
    }

    /**
     * @throws Exception
     */
    public function get(ProjectId $id): Project
    {
        $file = Path::join($id, $this->configFile);

        if (!file_exists($file))
            throw new Exception("project '$id' does not exist");

        $name = new ProjectName(basename($id));
        $state = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);

        return new Project(
            $id,
            $name,
            new StoreDsn(Assert::objectProp($state, 'migration_store', "migration_store")),
            $this->createEnvironmentMap($state, $name),
            $this->createHooks($state, $id->value),
            Assert::ifPropSet($state, 'single_transaction', true, 'single_transaction')->bool()->value()
        );
    }

    /**
     * @throws Exception
     */
    public function add(Project $project): void
    {
        $projectDir = $project->getId()->value;
        if (file_exists($projectDir))
            throw new Exception("project '{$project->getId()}' already exists");

        /* create project directory */
        (new Filesystem())->mkdir($projectDir);

        try {
            $this->update($project);
        }
        catch (Exception $e) {
            silent_call($this->remove(...), $project->getId());
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function update(Project $project): void
    {
        $state = Yaml::dump($project->snapshot(), 100, flags: Yaml::DUMP_OBJECT_AS_MAP);
        $file = Path::join($project->getId(), $this->configFile);
        if (@file_put_contents($file, $state) === false)
            throw new LastErrorException();
    }

    public function remove(ProjectId $id): void
    {
        (new Filesystem())->remove($id->value);
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
    private function createHooks(object $state, string $projectDir): array
    {
        $hooks = [];
        $rawHooks = Assert::ifProp($state, 'hooks', [], 'hooks')->array()->value();

        foreach ($rawHooks as $i => $hook) {
            $path = "hooks[$i]";

            $events = Assert::prop($hook, 'events', "$path.events");
            if (is_string($events))
                $events = [$events];

            $hooks[] = new Hook\Hook(
                new Hook\Action(Assert::stringProp($hook, 'action', "$path.action"), $projectDir),
                array_map(fn($e) => Hook\Event::from($e), Assert::that($events, 'events')->array()->notEmpty()->value()),
                Assert::ifProp($hook, 'timeout', Hook\Hook::DEFAULT_TIMEOUT, "$path.timeout")->int()->value(),
                Assert::ifProp($hook, 'fail_on_error', true, "$path.fail_on_error")->bool()->value(),
                Assert::ifProp($hook, 'arguments', [], "$path.arguments")->array()->value(),
                Assert::ifProp($hook, 'headers', (object) [], "$path.arguments")->object()->value()
            );
        }

        return $hooks;
    }
}