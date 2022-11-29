<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Location;
use GuzzleHttp\Exception\GuzzleException;

class GitFile extends File
{
    public function __construct(private readonly GitAdapter $git, Location $location, private readonly Checksum $sha)
    {
        parent::__construct($location);
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(): string
    {
        return $this->git->getContentsBySha($this->sha->value);
    }

    public function getChecksum(): Checksum
    {
        return $this->sha;
    }
}