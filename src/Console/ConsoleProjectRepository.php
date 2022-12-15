<?php

namespace Cinch\Console;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Cinch\LastErrorException;
use Cinch\Project\EnvironmentMap;
use Cinch\Project\Hook;
use Cinch\Project\HookEvent;
use Cinch\Project\HookScript;
use Cinch\Project\Project;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectName;
use Cinch\Project\ProjectRepository;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path as PathUtils;
use Symfony\Component\Yaml\Yaml;

class ConsoleProjectRepository implements ProjectRepository
{
    const PROJECT_FILE = 'project.yml';

    /**
     * @throws Exception
     */
    public function get(ProjectId $id): Project
    {
        $file = PathUtils::join($id, self::PROJECT_FILE);

        if (!file_exists($file))
            throw new Exception("project '$id' does not exist");

        $name = new ProjectName(basename($id));
        $state = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP);

        return new Project(
            $id,
            $name,
            new Dsn(Assert::stringProp($state, 'migration_store', "migration_store")),
            $this->createEnvironmentMap($state, $name),
            $this->createHooks($state)
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
        $fs = new Filesystem();
        $fs->mkdir(PathUtils::join($projectDir, 'log')); // creates all parents

        try {
            $this->update($project);
        }
        catch (Exception $e) {
            ignoreException($this->remove(...), $project->getId());
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function update(Project $project): void
    {
        $state = Yaml::dump($project->snapshot(), 100, flags: Yaml::DUMP_OBJECT_AS_MAP);
        $file = PathUtils::join($project->getId(), self::PROJECT_FILE);
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
        $createSchema = Environment::DEFAULT_CREATE_SCHEMA;
        $deployTimeout = Environment::DEFAULT_DEPLOY_TIMEOUT;

        /* 'env_name: dsn' */
        if (is_string($value)) {
            $targetDsn = $historyDsn = new Dsn($value);
        }
        /* 'env_name: {target: dsn, history: dsn}', history optional */
        else if (is_object($value)) {
            if (property_exists($value, 'deploy_timeout'))
                $deployTimeout = Assert::that($value->deploy_timeout, "$path.deploy_timeout")
                    ->int()->greaterThanEqualTo(0)->value();

            $target = Assert::thatProp($value, 'target', "$path.target")
                ->string()->notEmpty()->value();

            /* history key not present */
            if (!property_exists($value, 'history')) {
                $history = $target;
            }
            /* 'history: dsn' */
            else if (is_string($value->history)) {
                $history = $value->history;
            }
            /* 'history: {}' */
            else if (is_object($value->history)) {
                $h = $value->history;

                $history = Assert::ifPropSet($h, 'dsn', $target, "$path.history")
                    ->string()->notEmpty()->value();

                $schema = Assert::ifPropSet($h, 'schema', $schema, "$path.schema")
                    ->string()->value();

                $tablePrefix = Assert::ifPropSet($h, 'table_prefix', $tablePrefix, "$path.table_prefix")
                    ->string()->value();

                $createSchema = Assert::ifPropSet($h, 'create_schema',
                    $createSchema, "$path.create_schema")->bool()->value();
            }
            else {
                throw new Exception("$path.history must be a string|object, found " .
                    get_debug_type($value->history));
            }

            $targetDsn = new Dsn($target);
            $historyDsn = new Dsn($history);
        }
        else {
            throw new AssertException("$path must be an object|string, found " . get_debug_type($value));
        }

        return new Environment($targetDsn, $historyDsn, $schema, $tablePrefix, $deployTimeout, $createSchema);
    }

    /**
     * @throws Exception
     */
    private function createHooks(object $state): array
    {
        $hooks = [];
        $rawHooks = Assert::ifProp($state, 'hooks', (object) [], 'hooks')->object()->value();

        foreach ($rawHooks as $name => $hook) {
            $path = "hooks.$name";
            $hooks[$name] = new Hook(
                new HookScript(Assert::stringProp($hook, 'script', "$path.script")),
                HookEvent::from(Assert::stringProp($hook, 'event', "$path.event")),
                Assert::ifProp($hook, 'timeout', Hook::DEFAULT_TIMEOUT, "$path.timeout")->int()->value(),
                $hook->rollback ?? true,
                (array) ($hook->arguments ?? [])
            );
        }

        return $hooks;
    }
}