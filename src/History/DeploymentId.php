<?php

namespace Cinch\History;

use Cinch\Common\SingleValue;

class DeploymentId extends SingleValue
{
    public function __construct(int $value)
    {
        parent::__construct($value);
    }
}