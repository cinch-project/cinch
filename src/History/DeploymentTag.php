<?php

namespace Cinch\History;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;
use Ramsey\Uuid\Uuid;

class DeploymentTag extends SingleValue
{
    public function __construct(string|null $tag = null)
    {
        if ($tag === null)
            $tag = Uuid::uuid7()->toString();
        parent::__construct(Assert::betweenLength($tag, 1, 128, message: 'tag'));
    }
}