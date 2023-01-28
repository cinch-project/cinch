<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class Checksum extends SingleValue
{
    /** Creates a checksum using SHA-256.
     * @param string $data
     * @return Checksum
     */
    public static function fromData(string $data): Checksum
    {
        return new self(hash('sha256', $data));
    }

    public function __construct(string $checksum)
    {
        $n = strlen($checksum);
        if (($n % 2) != 0 || $n < 32 || $n > 64)
            throw new AssertException("invalid checksum, expected even length between 32 and 64");
        parent::__construct(Assert::xdigit(strtolower($checksum), 'checksum'));
    }
}
