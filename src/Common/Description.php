<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;

class Description extends SingleValue
{
    public function __construct(string $author)
    {
        parent::__construct(Assert::betweenLength($author, 1, 255, message: 'description'));
    }
}
