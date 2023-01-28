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
        $wherePolicyStatus = $this->wherePolicyStatus($policies, $statuses, $params);

        return $this->restoreChanges($this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change $pathIn group by path
            ) c2 on c.deployed_at = c2.deployed_at $wherePolicyStatus order by c.deployed_at desc", $params
        ));
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
        $wherePolicyStatus = $this->wherePolicyStatus($policies, $statuses, $params);

        return $this->restoreChanges($this->session->executeQuery("
            select c.* from $change c join (
                select max(c1.deployed_at) as deployed_at from $change c1, $deployment d
                where d.tag = ? and c1.deployed_at > d.ended_at group by c1.path
            ) t on c.deployed_at = t.deployed_at $wherePolicyStatus order by c.deployed_at desc", $params
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
        $wherePolicyStatus = $this->wherePolicyStatus($policies, $statuses, $params);

        return $this->restoreChanges($this->session->executeQuery("
            select c.* from $change c join (
                select max(deployed_at) as deployed_at from $change where deployed_at > ? group by path
            ) t on c.deployed_at = t.deployed_at $wherePolicyStatus order by c.deployed_at desc", $params
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
        if ($this->session->getPlatform()->getName() == 'sqlsrv') {
            $wherePolicyStatus = $this->wherePolicyStatus($policies, $statuses, $params);

            $query = "
                select top $count c.* from $change c join (
                    select max(deployed_at) as deployed_at from $change group by path
                ) c2 on c.deployed_at = c2.deployed_at $wherePolicyStatus order by c.deployed_at desc;";
        }
        else {
            $wherePolicyStatus = $this->whereIn($policies, $params, 'and');
            $wherePolicyStatus .= $this->whereIn($statuses, $params, 'and');

            $query = "
                select c.* from $change c left join $change c2 
                on c.path = c2.path and c.deployed_at < c2.deployed_at
                where c2.deployed_at is null $wherePolicyStatus order by c.deployed_at desc limit $count";
        }

        return $this->restoreChanges($this->session->executeQuery($query, $params));
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function findFirstRollbackToTag(): string|null
    {
        $change = $this->schema->table('change');
        $deployment = $this->schema->table('deployment');

        $params = [DeploymentCommand::MIGRATE->value, MigratePolicy::ONCE->value, ChangeStatus::MIGRATED->value];

        /* select the last two migrate deployments that have at least one change */
        $rows = $this->session->executeQuery("
            select c.tag from $deployment d left join $change c on d.tag = c.tag 
                where d.command = ? and c.migrate_policy = ? and c.status = ?
                group by c.tag, d.ended_at having count(c.tag) > 0 order by d.ended_at desc limit 2", $params
        )->fetchAllNumeric();

        return count($rows) == 2 ? $rows[1][0] : null;
    }

    /**
     * @return Change[]
     * @throws Exception
     */
    private function restoreChanges(Result $r): array
    {
        return array_map(fn (array $row) => Change::restore($row), $r->fetchAllAssociative());
    }

    /**
     * @param MigratePolicy[] $policies
     * @param ChangeStatus[] $statuses
     * @param array $params
     * @return string
     */
    private function wherePolicyStatus(array $policies, array $statuses, array &$params): string
    {
        $policyIn = $this->whereIn($policies, $params, 'where');
        return $policyIn . $this->whereIn($statuses, $params, $policyIn ? 'and' : 'where');
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

        $column = $alias ? "$alias." : '';

        if ($values[0] instanceof MigratePolicy)
            $column .= 'migrate_policy';
        else if ($values[0] instanceof ChangeStatus)
            $column .= 'status';
        else
            $column .= 'path';

        if (count($values) == 1) {
            $params[] = $values[0]->value;
            return sprintf(' %s %s = ? ', $prefix, $column);
        }

        $placeholders = '';

        foreach ($values as $v) {
            $params[] = $v->value; // string backed enum or inherits from Cinch\Common\SingleValue
            $placeholders .= ($placeholders ? ',?' : '?');
        }

        return sprintf(' %s %s in (%s) ', $prefix, $column, $placeholders);
    }
}
