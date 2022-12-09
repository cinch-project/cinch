<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Location;
use Exception;

class GitFile extends File
{
    public function __construct(
        private readonly GitAdapter $git,
        Location $location,
        private readonly Checksum $sha,
        private string|null $content = null)
    {
        parent::__construct($location);
    }

    /**
     * @throws Exception
     */
    public function getContents(): string
    {
        if ($this->content === null)
            $this->content = $this->git->getContents($this->location->value);
        return $this->content;
    }

    public function getChecksum(): Checksum
    {
        return $this->sha;
    }
}