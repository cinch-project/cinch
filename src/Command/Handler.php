<?php

namespace Cinch\Command;

use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

abstract class Handler
{
    /** @var Task[] */
    private array $tasks = [];
    protected readonly LoggerInterface $logger;
    private readonly EventDispatcherInterface $dispatcher;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    protected function addTask(Task $task): static
    {
        $this->tasks[] = $task;

        $task->setLogger($this->logger);
        $task->setEventDispatcher($this->dispatcher);
        $task->setId(count($this->tasks));

        return $this;
    }

    protected function getTaskCount(): int
    {
        return count($this->tasks);
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