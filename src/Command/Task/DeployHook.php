<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Hook;

class DeployHook extends Task
{
    public function __construct(
        private readonly Hook\Hook $hook,
        private readonly Hook\Event $event,
        private readonly Deploy|null $deployTask,
        private readonly Hook\Runner $hookRunner)
    {
        $action = $this->hook->action;
        parent::__construct(
            "hook: $event->value",
            sprintf('%s: %s', $action->getType()->value, $action->getPath())
        );
    }

    protected function doRun(): void
    {
        $this->hookRunner->runHook($this->hook, $this->event, $this->deployTask?->getChange());
    }

    protected function doUndo(): void
    {
    }
}
