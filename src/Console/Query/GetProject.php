<?php

namespace Cinch\Console\Query;

use Cinch\Project\ProjectId;

class GetProject
{
    public function __construct(public readonly ProjectId $projectId)
    {
    }
}