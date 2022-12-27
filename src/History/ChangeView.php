<?php

namespace Cinch\History;

use Cinch\Common\MigratePolicy;
use Cinch\Common\StorePath;
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

    /** Gets the most recent change for one or more paths.
     * @param StorePath[] $paths if empty, query the most recent change for all paths
     * @param MigratePolicy[] $policies
     * @param ChangeStatus[] $statuses
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChanges(array $paths = [], array $policies = [], array $statuses = []): array
    {
        $change = $this->schema->table('change');

        $params = [];
        $pathIn = $this->whereIn($paths, $params, 'where', alias: '');
        $policyIn = $this->whereIn($policies, $params, 'where');
        $statusIn = $this->whereIn($statuses, $params, $policyIn ? 'and' : 'where');

        $result = $this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change $pathIn group by path
            ) c2 on c.deployed_at = c2.deployed_at $policyIn $statusIn order by c.deployed_at desc", $params
        );

        return $this->getChangesFromResult($result);
    }

    /** Gets the most recent changes since a specific tag. This is used by rollbacks.
     * @note Changes marked as rollbacked are excluded.
     * @param DeploymentTag $tag
     * @param MigratePolicy[] $policies
     * @param ChangeStatus[] $statuses
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesSinceTag(DeploymentTag $tag, array $policies = [], array $statuses = []): array
    {
        $change = $this->schema->table('change');
        $deployment = $this->schema->table('deployment');

        $params = [$tag->value];
        $policyIn = $this->whereIn($policies, $params, 'where');
        $statusIn = $this->whereIn($statuses, $params, $policyIn ? 'and' : 'where');

        return $this->getChangesFromResult($this->session->executeQuery("
            select c.* from $change c join (
                select max(c1.deployed_at) as deployed_at from $change c1, $deployment d
                where d.tag = ? and c1.deployed_at > d.ended_at group by c1.path
            ) t on c.deployed_at = t.deployed_at $policyIn $statusIn order by c.deployed_at desc", $params
        ));
    }

    /** Gets the most recent changes since a specific datetime. This is used by rollbacks.
     * @note Changes marked as rollbacked are excluded.
     * @param DateTimeInterface $date
     * @param MigratePolicy[] $policies
     * @param ChangeStatus[] $statuses
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesSinceDate(DateTimeInterface $date, array $policies = [],
        array $statuses = []): array
    {
        $change = $this->schema->table('change');

        $params = [$this->session->getPlatform()->formatDateTime($date)];
        $policyIn = $this->whereIn($policies, $params, 'where');
        $statusIn = $this->whereIn($statuses, $params, $policyIn ? 'and' : 'where');

        return $this->getChangesFromResult($this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change where deployed_at > ? group by path
            ) t on c.deployed_at = t.deployed_at $policyIn $statusIn order by c.deployed_at desc", $params
        ));
    }

    /** Gets the most recent number of changes. This is used by rollbacks.
     * @param int $count
     * @param MigratePolicy[] $policies
     * @param ChangeStatus[] $statuses
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getMostRecentChangesByCount(int $count, array $policies = [], array $statuses = []): array
    {
        if ($count <= 0)
            return [];

        $params = [];
        $change = $this->schema->table('change');

        /* sql server performed terribly using the LEFT JOIN technique. */
        if ($this->session->getPlatform()->getName() == 'mssql') {
            $policyIn = $this->whereIn($policies, $params, 'where');
            $statusIn = $this->whereIn($statuses, $params, $policyIn ? 'and' : 'where');

            $query = "
                select top $count c.* from $change c join (
                    select max(deployed_at) as deployed_at from $change group by path
                ) c2 on c.deployed_at = c2.deployed_at $policyIn $statusIn order by c.deployed_at desc;";
        }
        else {
            $policyIn = $this->whereIn($policies, $params, 'and');
            $statusIn = $this->whereIn($statuses, $params, 'and');

            $query = "
                select c.* from $change c left join $change c2 
                on c.path = c2.path and c.deployed_at < c2.deployed_at
                where c2.deployed_at is null $policyIn $statusIn order by c.deployed_at desc limit $count";
        }

        return $this->getChangesFromResult($this->session->executeQuery($query, $params));
    }

    /**
     * @return Change[]
     * @throws Exception
     */
    private function getChangesFromResult(Result $r): array
    {
        return array_map(fn(array $row) => Change::restore($row), $r->fetchAllAssociative());
    }

    /**
     * @param MigratePolicy[]|ChangeStatus[]|StorePath[] $values
     * @param array $params
     * @param string $prefix can be 'where', 'and', 'or'
     * @param string $alias table alias
     * @return string
     */
    private function whereIn(array $values, array &$params, string $prefix, string $alias = 'c'): string
    {
        if (!$values)
            return '';

        $placeholders = '';

        foreach ($values as $v) {
            $params[] = $v->value; // string backed enum or Cinch\Common\SingleValue
            $placeholders .= ($placeholders ? ',' : '') . '?';
        }

        $column = $alias ? "$alias." : '';

        if ($values[0] instanceof MigratePolicy)
            $column .= 'migrate_policy';
        else if ($values[0] instanceof ChangeStatus)
            $column .= 'status';
        else
            $column .= 'path';

        return sprintf('%s %s in (%s)', $prefix, $column, $placeholders);
    }
}