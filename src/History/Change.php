<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\Checksum;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\Location;
use Cinch\Common\MigratePolicy;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class Change
{
    public function __construct(
        public readonly Location $location,
        public readonly DeploymentTag $tag,
        public readonly MigratePolicy $migratePolicy,
        public readonly ChangeStatus $status,
        public readonly Author $author,
        public readonly Checksum $checksum,
        public readonly Description $description,
        public readonly Labels $labels,
        public readonly DateTimeInterface $authoredAt,
        public readonly DateTimeInterface $deployedAt)
    {
    }

    /** Restores a Change object.
     * @param array $snapshot
     * @return Change
     * @throws Exception
     */
    public static function restore(array $snapshot): Change
    {
        return new Change(
            new Location($snapshot['location']),
            new DeploymentTag($snapshot['tag']),
            MigratePolicy::from($snapshot['migrate_policy']),
            ChangeStatus::from($snapshot['status']),
            new Author($snapshot['author']),
            new Checksum($snapshot['checksum']),
            new Description($snapshot['description']),
            Labels::restore($snapshot['labels']),
            new DateTimeImmutable($snapshot['authored_at']),
            new DateTimeImmutable($snapshot['deployed_at']),
        );
    }
}