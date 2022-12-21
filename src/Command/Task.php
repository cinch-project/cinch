<?php

namespace Cinch\Command;

use Cinch\Command\Task\EndedEvent;
use Cinch\Command\Task\StartedEvent;
use Cinch\Io;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class Task
{
    protected readonly Io $io;
    private readonly EventDispatcherInterface $dispatcher;
    private bool $success = false;
    private int $id = 0;
    private readonly string $name;
    private readonly string $description;
    private readonly bool $canUndo;

    public function __construct()
    {
        $attrs = (new \ReflectionObject($this))->getAttributes(TaskAttribute::class);
        if (!$attrs)
            throw new \RuntimeException("task must define an " . TaskAttribute::class . " attribute");

        $asTask = $attrs[0]->newInstance();
        $this->name = $asTask->name;
        $this->description = $asTask->description;
        $this->canUndo = $asTask->canUndo;
    }

    public function setId(int $id): void
    {
        $this->id = max(0, $id);
    }

    public function setIo(Io $io): void
    {
        $this->io = $io;
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

    public function undo(): void
    {
        if ($this->success && $this->canUndo)
            /** @noinspection PhpUnhandledExceptionInspection */
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

            $this->io->debug(sprintf('%s::undo: %s - %s',
                static::class, get_class($e), $e->getMessage()));
        }
        finally {
            $elapsed = (hrtime(true) - $startTime) / 1e9;
            $this->dispatcher->dispatch(new EndedEvent($this->success, $elapsed));
        }
    }
}