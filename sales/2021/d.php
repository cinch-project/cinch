<?php /** @noinspection ALL */

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Database\Session;
use Cinch\MigrationStore\Script\Script;

return new class extends Script {
    public function __construct()
    {
        parent::__construct(
            MigratePolicy::from('once'),
            new Author('andrew'),
            new DateTimeImmutable('2022-12-24 00:00:00'),
            new Description('testing'),
            new Labels()
        );
    }

    public function migrate(Session $session): void
    {
    }

    public function rollback(Session $session): void
    {
    }
};