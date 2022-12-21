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
    private bool $requiresRollback = false;

    /**
     * @param string $name task name
     * @param string $description brief description of the task
     * @param string $rollbackName name used when rolling back task. note: empty string disables rollback
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $rollbackName = '')
    {
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
    protected abstract function doRollback(): void;

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->execute(isRollback: false);
        $this->requiresRollback = !!$this->rollbackName;
    }

    public function rollback(): void
    {
        if ($this->requiresRollback)
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->execute(isRollback: true);
    }

    /**
     * @throws Exception
     */
    private function execute(bool $isRollback): void
    {
        $name = $isRollback ? $this->rollbackName : $this->name;
        $this->dispatcher->dispatch(new StartedEvent($name, $this->description, $isRollback));

        try {
            $success = false;
            $startTime = hrtime(true);
            $isRollback ? $this->doRollback() : $this->doRun();
            $success = true;
        }
        catch (Exception $e) {
            if (!$isRollback)
                throw $e;

            $this->io->debug(sprintf('%s::rollback: %s - %s',
                static::class, get_class($e), $e->getMessage()));
        }
        finally {
            $elapsed = (hrtime(true) - $startTime) / 1e9;
            $this->dispatcher->dispatch(new EndedEvent($success, $elapsed));
        }
    }
}