<?php

namespace Cinch\Command;

use Cinch\Io;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class CommandHandler
{
    /** @var Task[] */
    private array $tasks = [];
    protected readonly Io $io;
    protected readonly EventDispatcherInterface $dispatcher;

    public function setIo(Io $io): void
    {
        $this->io = $io;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
        Task::setEventDispatcher($dispatcher);
    }

    protected function addTask(Task $task): static
    {
        $this->tasks[] = $task;
        return $this;
    }

    /**
     * @throws Exception
     */
    protected function runTasks(): void
    {
        try {
            foreach ($this->tasks as $task)
                $task->run();
        }
        catch (Exception $e) {
            while ($task = array_pop($this->tasks))
                $task->rollback();
            throw $e;
        }
    }
}