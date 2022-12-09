<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\Project\Project;
use DateTimeInterface;

class RollbackCommand
{
    public function __construct(
        public readonly Project $project,
        public readonly Author $deployer,
        public readonly RollbackType $type,
        public readonly string|null $tag = null,
        public readonly int|null $count = null,
        public readonly DateTimeInterface|null $date = null,
        public readonly array $scripts = [],
        public readonly string $envName = '')
    {
        switch ($this->type) {
            case RollbackType::COUNT:
                if ($this->count === null)
                    throw new \InvalidArgumentException("rollback count cannot be null");
                break;

            case RollbackType::TAG:
                if (!$this->tag)
                    throw new \InvalidArgumentException("rollback tag cannot be empty or null");
                break;

            case RollbackType::DATE:
                if ($this->date === null)
                    throw new \InvalidArgumentException("rollback date cannot be null");
                break;

            case RollbackType::SCRIPT:
                if (!$this->scripts)
                    throw new \InvalidArgumentException("rollback script(s) cannot be empty");
                break;
        }
    }
}