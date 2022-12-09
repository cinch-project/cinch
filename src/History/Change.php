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
        public readonly Status $status,
        public readonly Author $author,
        public readonly Checksum $checksum,
        public readonly Description $description,
        public readonly DateTimeInterface $authoredAt,
        public readonly DateTimeInterface $deployedAt)
    {
    }

    /** Normalizes this change object to an array.
     *
     * Prototype for $formatDateTime parameter.
     * ```
     * formatDateTime(DateTimeInterface|null $dt = null): string
     * ```
     * @param callable|null $formatDateTime If this is null, timestamps will be formatted as 'Y-m-d H:i:s.uP'.
     * @return array
     */
    public function normalize(callable|null $formatDateTime = null): array
    {
        if ($formatDateTime === null)
            $formatDateTime = self::formatDateTime(...);

        return [
            'location' => $this->location->value,
            'deployment_id' => $this->deploymentId->value,
            'migrate_policy' => $this->migratePolicy->value,
            'status' => $this->status->value,
            'author' => $this->author->value,
            'checksum' => $this->checksum->value,
            'description' => $this->description->value,
            'authored_at' => $formatDateTime($this->authoredAt),
            'deployed_at' => $formatDateTime()
        ];
    }

    /** Denormalizes an array to a Change object.
     * @param array $data
     * @return Change
     * @throws Exception
     */
    public static function denormalize(array $data): Change
    {
        return new Change(
            new Location($data['location']),
            new DeploymentId($data['deployment_id']),
            MigratePolicy::from($data['migrate_policy']),
            Status::from($data['status']),
            new Author($data['author']),
            new Checksum($data['checksum']),
            new Description($data['description']),
            new DateTimeImmutable($data['authored_at']),
            new DateTimeImmutable($data['deployed_at']),
        );
    }

    /**
     * @param DateTimeInterface|null $dt
     * @return string
     * @throws Exception
     */
    private static function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTimeImmutable(timezone: new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.uP');
    }
}