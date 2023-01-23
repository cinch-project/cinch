<?php

namespace Cinch\Console\Query;

use Cinch\Project\ProjectName;

class GetProject
{
    public function __construct(public readonly ProjectName $projectName)
    {
    }
}