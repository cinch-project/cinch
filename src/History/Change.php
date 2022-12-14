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

    /** Denormalizes an array to a Change object.
     * @param array $data
     * @return Change
     * @throws Exception
     */
    public static function hydrate(array $data): Change
    {
        return new Change(
            new Location($data['location']),
            new DeploymentTag($data['tag']),
            MigratePolicy::from($data['migrate_policy']),
            ChangeStatus::from($data['status']),
            new Author($data['author']),
            new Checksum($data['checksum']),
            new Description($data['description']),
            Labels::hydrate($data['labels']),
            new DateTimeImmutable($data['authored_at']),
            new DateTimeImmutable($data['deployed_at']),
        );
    }
}