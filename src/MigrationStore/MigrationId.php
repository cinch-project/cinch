<?php

namespace Cinch\MigrationStore;

use Cinch\Common\SingleValue;
use Cinch\Component\Assert\Assert;

class MigrationId extends SingleValue
{
    /**
     * @param string $id between 1-64 bytes, can contain a-zA-Z, digits, underscores, hyphens, colons,
     * semi-colon, commas and forward slashes.
     */
    public function __construct(string $id)
    {
        parent::__construct(Assert::regex($id, '~[\w\-:.;,/]{1,64}~', 'migration id'));
    }
}