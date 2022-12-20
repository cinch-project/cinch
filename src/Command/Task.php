<?php

namespace Cinch\Command;

use Cinch\Command\Task\TaskEnded;
use Cinch\Command\Task\TaskStarted;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class Task
{
    private static EventDispatcherInterface $dispatcher;
    private int $startTime = 0;
    private bool $requiresRollback = false;

    /**
     * @param string $name task name
     * @param string $message a message describing the task
     * @param string|null $rollbackName name used when rolling back task, set to null if task has no rollback
     */
    public function __construct(
        private readonly string $name,
        private readonly string $message,
        private readonly string|null $rollbackName = null)
    {
    }

    public static function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    /**
     * @throws Exception
     */
    protected abstract function doRun(): void;

    /**
     * @throws Exception
     */
    protected abstract function doRollback(): void;

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->execute(false);
        $this->requiresRollback = true;
    }

    public function rollback(): void
    {
        if ($this->requiresRollback && $this->rollbackName !== null)
            $this->execute(true);
    }

    /**
     * @throws Exception
     */
    private function execute(bool $rollback): void
    {
        $success = false;
        $name = $rollback ? $this->rollbackName : $this->name;
        self::$dispatcher->dispatch(new TaskStarted($name, $this->message, rollback: $rollback));

        try {
            $this->startTime = hrtime(true);
            $rollback ? $this->doRollback() : $this->doRun();
            $success = true;
        }
        catch (Exception $e) {
            if (!$rollback)
                throw $e;
        }
        finally {
            $elapsed = (hrtime(true) - $this->startTime) / 1e9;
            $this->startTime = 0;
            self::$dispatcher->dispatch(new TaskEnded($success, $elapsed));
        }
    }
}