<?php

namespace Cinch\Command;

use Cinch\History\Change;
use Cinch\History\DeploymentId;
use Cinch\History\Status;
use Cinch\MigrationStore\Migration;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Symfony\Component\Filesystem\Path;

abstract class AbstractDeployHandler implements CommandHandler
{
    protected function toDeploymentError(Exception $e): array
    {
        /* make paths relative to project root, which is "../.." from here */
        $base = dirname(__DIR__, 2);
        $trace = preg_split('~\R~', $e->getTraceAsString(), flags: PREG_SPLIT_NO_EMPTY);

        /* replace each trace path with a relative path */
        foreach ($trace as &$t)
            $t = preg_replace_callback('~^#\d+ (/.+)\(\d+\)~', fn($m) => Path::makeRelative($m[1], $base), $t);

        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => Path::makeRelative($e->getFile(), $base),
            'line' => $e->getLine(),
            'trace' => $trace
        ];
    }

    /**
     * @param DeploymentId $deploymentId
     * @param Status $status
     * @param Migration $migration
     * @return Change
     * @throws Exception
     */
    protected function createChange(DeploymentId $deploymentId, Status $status, Migration $migration): Change
    {
        return new Change(
            $migration->location,
            $deploymentId,
            $migration->script->getMigratePolicy(),
            $status,
            $migration->script->getAuthor(),
            $migration->checksum,
            $migration->script->getDescription(),
            $migration->script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC'))
        );
    }
}