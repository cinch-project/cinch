<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Common\Description;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class SqlScriptParser
{
    const TAGS = ['author', 'authored_at', 'description', 'migrate_policy'];

    /**
     * @throws Exception
     */
    public function parse(string $data): Script
    {
        $args = self::parseTags($data);
        [$migrateSql, $rollbackSql] = self::parseSql($data);

        if ($migrateSql && $rollbackSql)
            return new SqlScript($migrateSql, $rollbackSql, ...$args);

        if ($migrateSql)
            return new SqlMigrateScript($migrateSql, ...$args);

        return new SqlRollbackScript($rollbackSql, ...$args);
    }

    /**
     * @throws Exception
     */
    private static function parseTags(string $data): array
    {
        // Format is a DocBlock
        // /**
        //  * @author ...
        //  * @authoredAt ...
        //  * etc.
        //  */
        if (preg_match('~/\*\*(.*)\*/~SsU', $data, $matches) !== 1)
            throw new AssertException("missing entry comment: /** @tags... */");

        $tags = [];

        foreach (preg_split('~\R~', trim($matches[1]), flags: PREG_SPLIT_NO_EMPTY) as $line) {
            $line = rtrim(ltrim($line, "* \t\n\r\0\x0B"));
            if ($line && $line[0] == '@' && ($line = substr($line, 1))) {
                $parts = preg_split('~[ \t]+~', $line, 2, PREG_SPLIT_NO_EMPTY);
                if (in_array($parts[0], self::TAGS))
                    $tags[$parts[0]] = $parts[1] ?? null;
            }
        }

        Assert::keySet($tags, 'author', "@author");
        Assert::keySet($tags, 'authored_at', "@authored_at");
        Assert::keySet($tags, 'description', "@description");
        Assert::keySet($tags, 'migrate_policy', "@migrate_policy");

        try {
            $authoredAt = new DateTimeImmutable($tags['authored_at'], new DateTimeZone('UTC'));
        }
        catch (Exception $e) {
            throw new AssertException("@authored_at invalid - {$e->getMessage()}");
        }

        return [
            MigratePolicy::from($tags['migrate_policy']),
            new Author($tags['author']),
            $authoredAt,
            new Description($tags['description'])
        ];
    }

    /**
     * @throws Exception
     */
    private static function parseSql(string $data): array
    {
        /* find "-- @migrate" or "-- @rollback", ignoring whitespace */
        static $pattern = '~^[ \t]*--[ \t]+@(migrate|rollback)\b~Sm';

        $count = preg_match_all($pattern, $data, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        if (!$count)
            throw new AssertException("invalid entry: no migrate or rollback section found.");

        if ($count > 2)
            throw new AssertException("invalid entry: multiple migrate and/or rollback sections");

        $migrateOff = -1;
        $rollbackOff = -1;
        $migrateSql = null;
        $rollbackSql = null;

        /* one or two sections: can only be 'migrate' and 'rollback' */
        foreach ($matches as $m) {
            if ($m[1][0] == 'migrate')
                $migrateOff = $m[0][1];
            else
                $rollbackOff = $m[0][1];
        }

        if ($rollbackOff == -1) {
            $migrateSql = substr($data, $migrateOff);
        }
        else if ($migrateOff == -1) {
            $rollbackSql = substr($data, $rollbackOff);
        }
        /* rollback section declared first */
        else if ($rollbackOff < $migrateOff) {
            $rollbackSql = substr($data, $rollbackOff, $migrateOff - $rollbackOff);
            $migrateSql = substr($data, $migrateOff);
        }
        /* migrate section declared first */
        else {
            $migrateSql = substr($data, $migrateOff, $rollbackOff - $migrateOff);
            $rollbackSql = substr($data, $rollbackOff);
        }

        return [$migrateSql, $rollbackSql];
    }
}