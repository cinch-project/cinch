<?php

namespace Cinch\History;

use Cinch\Component\Assert\Assert;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class SchemaVersion
{
    /* https://ihateregex.io/expr/semver/ */
    public const SEMVER_PATTERN =
        '^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)' .
        '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$';

    public readonly DateTimeInterface $releaseDate;

    /**
     * @throws Exception
     */
    public function __construct(
        public readonly string $version,
        public readonly string $description,
        string $releaseDate)
    {
        Assert::regex($this->version, '~' . self::SEMVER_PATTERN . '~', 'schema version');
        Assert::betweenLength($this->description, 1, 255, message: 'schema description');
        $this->releaseDate = new DateTimeImmutable($releaseDate, new DateTimeZone('UTC'));
    }
}
