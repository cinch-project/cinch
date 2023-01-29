<?php

namespace Cinch\Component\Schema;

use Exception;

class Builder
{
    /**
     * @param Session $session
     * @throws Exception
     */
    public function __construct(private readonly Session $session)
    {
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function createTable(string $name, string $options = ''): Table
    {
        return new Table($this->session, $name, $options);
    }
}
