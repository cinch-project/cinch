<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\CommitPolicy;
use Cinch\Common\Description;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class SqlScriptParser
{
    const TAGS = ['author', 'authored_at', 'description', 'commit_policy'];

    /**
     * @throws Exception
     */
    public function parse(string $data): Script
    {
        $args = self::parseTags($data);
        [$commitSql, $revertSql] = self::parseSql($data);

        if ($commitSql && $revertSql)
            return new SqlScript($commitSql, $revertSql, ...$args);

        if ($commitSql)
            return new SqlCommitScript($commitSql, ...$args);

        return new SqlRevertScript($revertSql, ...$args);
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
        Assert::keySet($tags, 'commit_policy', "@commit_policy");

        try {
            $authoredAt = new DateTimeImmutable($tags['authored_at'], new DateTimeZone('UTC'));
        }
        catch (Exception $e) {
            throw new AssertException("@authored_at invalid - {$e->getMessage()}");
        }

        return [
            CommitPolicy::from($tags['commit_policy']),
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
        /* find "-- @commit" or "-- @revert", ignoring whitespace */
        static $pattern = '~^[ \t]*--[ \t]+@(commit|revert)\b~Sm';

        $count = preg_match_all($pattern, $data, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        if (!$count)
            throw new AssertException("invalid entry: no commit or revert section found.");

        if ($count > 2)
            throw new AssertException("invalid entry: multiple commit and/or revert sections");

        $commitOff = -1;
        $revertOff = -1;
        $commitSql = null;
        $revertSql = null;

        /* one or two sections: can only be 'commit' and 'revert' */
        foreach ($matches as $m) {
            if ($m[1][0] == 'commit')
                $commitOff = $m[0][1];
            else
                $revertOff = $m[0][1];
        }

        if ($revertOff == -1) {
            $commitSql = substr($data, $commitOff);
        }
        else if ($commitOff == -1) {
            $revertSql = substr($data, $revertOff);
        }
        /* revert section declared first */
        else if ($revertOff < $commitOff) {
            $revertSql = substr($data, $revertOff, $commitOff - $revertOff);
            $commitSql = substr($data, $commitOff);
        }
        /* commit section declared first */
        else {
            $commitSql = substr($data, $commitOff, $revertOff - $commitOff);
            $revertSql = substr($data, $revertOff);
        }

        return [$commitSql, $revertSql];
    }
}