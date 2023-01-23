<?php

namespace Cinch\History;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class DeploymentTag extends SingleValue
{
    public function __construct(string|null $tag = null)
    {
        if ($tag === null)
            $tag = Ulid::generate();
        parent::__construct(Assert::betweenLength($tag, 1, 64, message: 'tag'));
    }
}