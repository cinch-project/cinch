<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\SingleValue;

class FileId extends SingleValue
{
    public function __construct(string $id = '')
    {
        parent::__construct($id);
    }
}