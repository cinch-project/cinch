<?php

namespace Cinch\Command;

use Cinch\Io;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class Handler
{
    /** @var Task[] */
    private array $tasks = [];
    protected readonly Io $io;
    private readonly EventDispatcherInterface $dispatcher;

    public function setIo(Io $io): void
    {
        $this->io = $io;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    protected function addTask(Task $task): static
    {
        $task->setIo($this->io);
        $task->setEventDispatcher($this->dispatcher);
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
            foreach ($this->tasks as $task)
                $task->undo();
            throw $e;
        }
    }
}