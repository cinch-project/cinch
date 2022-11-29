<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;

class Author extends SingleValue
{
    public function __construct(string $author)
    {
        parent::__construct(Assert::betweenLength($author, 1, 64, message: 'author'));
    }
}