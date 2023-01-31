<?php

namespace Cinch\Database;

use Cinch\Component\Schema\QueryResult;
use Doctrine\DBAL\Result;

/** Doctrine implementation of schema builder result */
class SchemaQueryResult implements QueryResult
{
    public function __construct(private readonly Result $result)
    {
    }

    public function fetchNumeric(): array|false
    {
        return $this->result->fetchNumeric();
    }

    public function fetchAssociative(): array|false
    {
        return $this->result->fetchAssociative();
    }

    public function fetchAllNumeric(): array|false
    {
        return $this->result->fetchAllNumeric();
    }

    public function fetchAllAssociative(): array|false
    {
        return $this->result->fetchNumeric();
    }

    public function rowCount(): int
    {
        return $this->result->rowCount();
    }

    public function columnCount(): int
    {
        return $this->result->columnCount();
    }

    public function free(): void
    {
        $this->result->free();
    }
}
