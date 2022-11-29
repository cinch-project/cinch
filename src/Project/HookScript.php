<?php

namespace Cinch\Project;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class HookScript extends SingleValue
{
    public function __construct(string $script)
    {
        parent::__construct(realpath(Assert::executable($script, 'hook script')));
    }
}