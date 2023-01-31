<?php

namespace Cinch\Component\Schema;

use Exception;

interface QueryResult
{
    /**
     * @throws Exception
     */
    public function fetchNumeric(): array|false;

    /**
     * @throws Exception
     */
    public function fetchAssociative(): array|false;

    /**
     * @throws Exception
     */
    public function fetchAllNumeric(): array|false;

    /**
     * @throws Exception
     */
    public function fetchAllAssociative(): array|false;

    /**
     * @throws Exception
     */
    public function rowCount(): int;

    /**
     * @throws Exception
     */
    public function columnCount(): int;

    public function free(): void;
}
