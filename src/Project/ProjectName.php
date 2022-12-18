<?php

namespace Cinch\Project;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class ProjectName extends SingleValue
{
    public function __construct(string $name)
    {
        Assert::notContains($name, '/', message: 'project name');
        Assert::regex($name, "~^[\x{0001}-\x{ffff}]{1,57}(?<!\s)$~u", 'project name');
        parent::__construct($name);
    }
}