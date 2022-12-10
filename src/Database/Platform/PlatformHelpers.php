<?php

namespace Cinch\Database\Platform;

use Cinch\Database\Session;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

trait PlatformHelpers
{
    private readonly float $version;
    private readonly string $name;

    public function getName(): string
    {
        if (!isset($this->name))
            $this->name = strtolower(substr(classname(static::class), 0, -8)); // 8 = strlen('Platform')
        return $this->name;
    }

    public function supportsTransactionalDDL(): bool
    {
        return true; /* default, since most have support */
    }

    public function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTime(timezone: new DateTimeZone('UTC'));
        return $dt->format(self::DATETIME_FORMAT);
    }

    public function getVersion(): float
    {
        return $this->version;
    }
}