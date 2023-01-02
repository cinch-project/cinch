<?php

namespace Cinch\Command;

use Cinch\Command\Task\EndedEvent;
use Cinch\Command\Task\StartedEvent;
use Cinch\Component\Assert\Assert;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use RuntimeException;

abstract class Task
{
    protected readonly LoggerInterface $logger;
    private readonly EventDispatcherInterface $dispatcher;
    private bool $success = false;
    private int $id = 0;
    private string $name;
    private string $description;
    private readonly bool $canUndo;

    public function __construct()
    {
        if (!($attrs = (new ReflectionObject($this))->getAttributes(TaskAttribute::class)))
            throw new RuntimeException(sprintf('%s must define %s',
                static::class, TaskAttribute::class));

        $attr = $attrs[0]->newInstance();
        $this->setName($attr->name);
        $this->setDescription($attr->description);
        $this->canUndo = $attr->canUndo;
    }

    /**
     * @param string $name
     */
    protected function setName(string $name): void
    {
        $this->name = Assert::notEmpty($name, 'task name');
    }

    protected function setDescription(string $description): void
    {
        $this->description = Assert::notEmpty($description, 'task description');
    }

    public function setId(int $id): void
    {
        $this->id = max(0, $id);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /** Runs the task.
     * @throws Exception
     */
    protected abstract function doRun(): void;

    /** Rolls back the task.
     * @throws Exception
     */
    protected abstract function doUndo(): void;

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->execute(false);
    }

    /**
     * @throws Exception
     */
    public function undo(): void
    {
        if ($this->success && $this->canUndo)
            $this->execute(true);
    }

    /**
     * @throws Exception
     */
    private function execute(bool $isUndo): void
    {
        $this->dispatcher->dispatch(new StartedEvent($this->id, $this->name, $this->description, $isUndo));

        try {
            $startTime = hrtime(true);
            $isUndo ? $this->doUndo() : $this->doRun();
            $this->success = true;
        }
        catch (Exception $e) {
            if (!$isUndo)
                throw $e;

            $this->logger->debug(sprintf('%s::undo: %s - %s',
                static::class, get_class($e), $e->getMessage()));
        }
        finally {
            $elapsed = (hrtime(true) - $startTime) / 1e9;
            $this->dispatcher->dispatch(new EndedEvent($this->success, $elapsed));
        }
    }
}