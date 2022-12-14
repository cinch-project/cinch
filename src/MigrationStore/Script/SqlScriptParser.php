<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
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
        return new SqlScript(...self::parseSql($data), ...$args);
    }

    /**
     * @throws Exception
     */
    private static function parseTags(string $data): array
    {
        // DocBlock
        // /**
        //  * @author ...
        //  * @authoredAt ...
        //  * etc.
        //  */
        if (preg_match('~/\*\*(.*)\*/~SsU', $data, $docBlock) !== 1)
            throw new AssertException("missing entry comment: /** @tags... */");

        $tags = [];
        $labels = [];

        foreach (preg_split('~\R~', trim($docBlock[1]), flags: PREG_SPLIT_NO_EMPTY) as $line) {
            /* remove possible DocBlock continuation line formatting: ' * @author blah' */
            $line = rtrim(ltrim($line, "* \t\n\r\0\x0B"));

            /* is this a tag? remove '@' and ensure result is not empty */
            if ($line && $line[0] == '@' && ($line = substr($line, 1))) {
                /* split on any number of spaces and/or tabs, limit split to two tokens: name and value */
                $pair = preg_split('~[ \t]+~', $line, 2, PREG_SPLIT_NO_EMPTY);

                $name = array_shift($pair);
                $value = array_shift($pair);

                if ($name == 'label')
                    $labels[] = $name;
                else if (in_array($name, self::TAGS))
                    $tags[$name] = $value;
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
            new Description($tags['description']),
            new Labels($labels)
        ];
    }

    /**
     * @throws Exception
     */
    private static function parseSql(string $data): array
    {
        $migrateSql = '';
        $rollbackSql = '';
        $section = '';

        foreach (preg_split('~\R~', $data) as $line => $data) {
            if (preg_match('~^[ \t]*--[ \t]+@(migrate|rollback)\b~S', $data, $m)) {
                $section = $m[1];

                if ($section == 'migrate' && $migrateSql)
                    throw new AssertException("second migrate section found at line $line");

                if ($section == 'rollback' && $rollbackSql)
                    throw new AssertException("second rollback section found at line $line");
            }
            else if ($section == 'migrate') {
                $migrateSql .= "$data\n";
            }
            else if ($section == 'rollback') {
                $rollbackSql .= "$data\n";
            }
        }

        if (!($migrateSql || $rollbackSql))
            throw new AssertException("no migrate or rollback section found");

        return [trim($migrateSql), trim($rollbackSql)];
    }
}