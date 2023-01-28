<?php

namespace Cinch\Project;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class ProjectName extends SingleValue
{
    public function __construct(string $name)
    {
        parent::__construct(Assert::that($name, 'project name')
            ->notContains('/', 'UTF-8')
            ->betweenLength(1, 128, 'UTF-8')
            ->value()
        );
    }
}
