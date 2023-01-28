<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Common\StorePath;
use Cinch\Project\ProjectName;
use DateTimeInterface;

class AddScript
{
    public function __construct(
        public readonly ProjectName $projectName,
        public readonly StorePath $path,
        public readonly MigratePolicy $migratePolicy,
        public readonly Author $author,
        public readonly DateTimeInterface $authoredAt,
        public readonly Description $description,
        public readonly Labels $labels)
    {
    }
}
