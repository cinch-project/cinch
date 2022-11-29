<?php

namespace Cinch\Project;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class ProjectName extends SingleValue
{
    public function __construct(string $name)
    {
        // leave room for cinch_ schema prefix (63 - 6)
        Assert::regex($name, "~^[\x{0001}-\x{ffff}]{1,57}(?<!\s)$~u", 'project name');
        parent::__construct($name);
    }
}