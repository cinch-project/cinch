<?php

namespace Cinch\History;

use Cinch\Common\Location;
use Cinch\Database\Session;
use DateTimeInterface;
use Doctrine\DBAL\Result;
use Exception;

class HistoryView
{
    private readonly Session $session;

    public function __construct(private readonly Schema $schema)
    {
        $this->session = $this->schema->session();
    }

    /** Gets the latest change for one or more locations. Used by migrate and rollback.
     * @param Location[] $locations
     * @param bool $excludeRollbacked indicates if changes in rollbacked status should be excluded from results
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getLatestChangeForLocations(array $locations, bool $excludeRollbacked = false): array
    {
        if (!$locations)
            return [];

        $placeholders = '';
        $params = [];
        $statusFilter = '';

        foreach ($locations as $l) {
            $params[] = $l->value;
            $placeholders .= ($placeholders ? ',' : '') . '?';
        }

        if ($excludeRollbacked) {
            $params[] = ChangeStatus::ROLLBACKED;
            $statusFilter = 'and status <> ?';
        }

        $table = $this->schema->table('change');
        return $this->getChangesFromResult($this->session->executeQuery("
            select * from $table where deployed_at in (
                select max(deployed_at) from $table where location in ($placeholders) group by location
            ) $statusFilter order by deployed_at desc", $params
        ));
    }

    /** Gets changes since the given tag. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param string $tag
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getChangesSinceTag(string $tag): array
    {
        if (!$tag)
            return [];

        return $this->getChangesFromResult($this->session->executeQuery("
            select * from {$this->schema->table('change')}  
                where deployed_at > (select ended_at from {$this->schema->table('deployment')} where tag = ?)
                    and status <> ? order by deployed_at desc", [$tag, ChangeStatus::ROLLBACKED]
        ));
    }

    /** Gets changes since the given datetime. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param DateTimeInterface $date
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getChangesSinceDate(DateTimeInterface $date): array
    {
        $params = [$this->session->getPlatform()->formatDateTime($date), ChangeStatus::ROLLBACKED];
        return $this->getChangesFromResult($this->session->executeQuery("
            select * from {$this->schema->table('change')} 
                where deployed_at > ? and status <> ? order by deployed_at desc", $params
        ));
    }

    /** Gets the last N number of changes. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param int $count
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getLastCountChanges(int $count): array
    {
        if ($count <= 0)
            return [];

        return $this->getChangesFromResult($this->session->executeQuery("
            select * from {$this->schema->table('change')} 
                where status <> ? order by deployed_at desc limit $count", [ChangeStatus::ROLLBACKED]
        ));
    }

    /**
     * @return Change[]
     * @throws Exception
     */
    private function getChangesFromResult(Result $r): array
    {
        return array_map(fn(array $row) => Change::hydrate($row), $r->fetchAllAssociative());
    }
}