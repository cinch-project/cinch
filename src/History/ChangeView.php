<?php

namespace Cinch\History;

use Cinch\Common\Location;
use Cinch\Database\Session;
use DateTimeInterface;
use Doctrine\DBAL\Result;
use Exception;

class ChangeView
{
    private readonly Session $session;

    public function __construct(private readonly Schema $schema)
    {
        $this->session = $this->schema->session();
    }

    /** Gets the most recent change for one or more locations.
     * @param Location[] $locations
     * @param bool $excludeRollbacked indicates if rollbacked changes should be excluded
     * @return array ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChanges(array $locations, bool $excludeRollbacked = false): array
    {
        if (!$locations)
            return [];

        $placeholders = '';
        $params = [];
        $whereStatus = '';

        foreach ($locations as $l) {
            $params[] = $l->value;
            $placeholders .= ($placeholders ? ',' : '') . '?';
        }

        if ($excludeRollbacked) {
            $params[] = ChangeStatus::ROLLBACKED;
            $whereStatus = 'where status <> ?';
        }

        $change = $this->schema->table('change');

        $result = $this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change where location in ($placeholders) group by location
            ) c2 on c.deployed_at = c2.deployed_at $whereStatus order by c.deployed_at desc", $params
        );

        return $this->getChangesFromResult($result);
    }

    /** Gets the most recent changes since a specific tag. This is used by rollbacks.
     * @note Changes marked as rollbacked are excluded.
     * @param DeploymentTag $tag
     * @return array ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesSinceTag(DeploymentTag $tag): array
    {
        $change = $this->schema->table('change');
        $deployment = $this->schema->table('deployment');
        $params = [$tag->value, ChangeStatus::ROLLBACKED];

        return $this->getChangesFromResult($this->session->executeQuery("
            select c.* from $change c join (
                select max(c1.deployed_at) as deployed_at from $change c1, $deployment d
                where d.tag = ? and c1.deployed_at > d.ended_at group by c1.location
            ) t on c.deployed_at = t.deployed_at where status <> ? order by c.deployed_at desc", $params
        ));
    }

    /** Gets the most recent changes since a specific datetime. This is used by rollbacks.
     * @note Changes marked as rollbacked are excluded.
     * @param DateTimeInterface $date
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesSinceDate(DateTimeInterface $date): array
    {
        $params = [$this->session->getPlatform()->formatDateTime($date), ChangeStatus::ROLLBACKED];
        $change = $this->schema->table('change');

        return $this->getChangesFromResult($this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change where deployed_at > ? group by location
            ) t on c.deployed_at = t.deployed_at where status <> ? order by c.deployed_at desc", $params
        ));
    }

    /** Gets the most recent number of changes. This is used by rollbacks.
     * @note Changes marked as rollbacked are excluded.
     * @param int $count
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesByCount(int $count): array
    {
        if ($count <= 0)
            return [];

        $change = $this->schema->table('change');

        /* sql server performed terribly using the LEFT JOIN technique. */
        if ($this->session->getPlatform()->getName() == 'mssql')
            $query = "
                select top $count c.* from $change c join (
                    select max(deployed_at) as deployed_at from $change group by location
                ) c2 on c.deployed_at = c2.deployed_at where status <> ? order by c.deployed_at desc;";
        else
            $query = "
                select c.* from $change c left join $change c2 
                on c.location = c2.location and c.deployed_at < c2.deployed_at
                where c2.deployed_at is null and status <> ? order by c.deployed_at desc limit $count";

        return $this->getChangesFromResult($this->session->executeQuery($query, [ChangeStatus::ROLLBACKED]));
    }

    /**
     * @return Change[]
     * @throws Exception
     */
    private function getChangesFromResult(Result $r): array
    {
        return array_map(fn(array $row) => Change::restore($row), $r->fetchAllAssociative());
    }
}