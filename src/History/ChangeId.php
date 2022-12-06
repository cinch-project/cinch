<?php

namespace Cinch\History;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class ChangeId extends SingleValue
{
    public function __construct(string $id)
    {
        parent::__construct(Assert::betweenLength($id, 1, 64, 'ASCII', 'change id'));
    }
}