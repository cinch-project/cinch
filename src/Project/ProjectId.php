<?php

namespace Cinch\Project;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class ProjectId extends SingleValue
{
    public function __construct(string $id)
    {
        parent::__construct(Assert::notEmpty($id, 'project id'));
    }
}