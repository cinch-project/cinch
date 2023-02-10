<?php

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Component\Schema\Builder;
use Cinch\MigrationStore\Script\Script;

return new class () extends Script {
    public function __construct()
    {
        parent::__construct(
            MigratePolicy::from('${migrate_policy}'),
            new Author('${author}'),
            new DateTimeImmutable('${authored_at}'),
            new Description('${description}'),
            new Labels(${labels})
        );
    }

    public function migrate(Builder $builder): void
    {
    }

    public function rollback(Builder $builder): void
    {
    }
};
