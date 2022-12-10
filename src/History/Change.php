<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\Checksum;
use Cinch\Common\Description;
use Cinch\Common\Location;
use Cinch\Common\MigratePolicy;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class Change
{
    public function __construct(
        public readonly Location $location,
        public readonly DeploymentId $deploymentId,
        public readonly MigratePolicy $migratePolicy,
        public readonly ChangeStatus $status,
        public readonly Author $author,
        public readonly Checksum $checksum,
        public readonly Description $description,
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
            new DeploymentId($data['deployment_id']),
            MigratePolicy::from($data['migrate_policy']),
            ChangeStatus::from($data['status']),
            new Author($data['author']),
            new Checksum($data['checksum']),
            new Description($data['description']),
            new DateTimeImmutable($data['authored_at']),
            new DateTimeImmutable($data['deployed_at']),
        );
    }
}