<?php

namespace Cinch\History;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class DeploymentId extends SingleValue
{
    public function __construct(int $value)
    {
        parent::__construct(Assert::greaterThanEqualTo($value, 1, 'deployment id'));
    }
}