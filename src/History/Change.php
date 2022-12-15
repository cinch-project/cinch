<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\Checksum;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\StorePath;
use Cinch\Common\MigratePolicy;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class Change
{
    public function __construct(
        public readonly StorePath $path,
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

    public function snapshot(callable $formatDateTime): array
    {
        return [
            'path' => $this->path->value,
            'tag' => $this->tag->value,
            'migrate_policy' => $this->migratePolicy->value,
            'status' => $this->status->value,
            'author' => $this->author->value,
            'checksum' => $this->checksum->value,
            'description' => $this->description->value,
            'labels' => $this->labels->snapshot(),
            'authored_at' => $formatDateTime($this->authoredAt),
            'deployed_at' => $formatDateTime($this->deployedAt)
        ];
    }

    /** Restores a Change object.
     * @param array $snapshot
     * @return Change
     * @throws Exception
     */
    public static function restore(array $snapshot): Change
    {
        return new Change(
            new StorePath($snapshot['path']),
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