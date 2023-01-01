<?php

namespace Cinch\MigrationStore;

use RuntimeException;

/** Used to build a tree of migration store directory files for the purpose of sorting.
 * @internal
 */
class MigrationNode
{
    /** @var MigrationNode[] */
    private array $children = [];

    public static function root(): self
    {
        return new self('', null);
    }

    private function __construct(private readonly string $name, private readonly Migration|null $migration)
    {
    }

    /** Adds a child to this node.
     * @param string $name
     * @param Migration|null $migration
     * @return MigrationNode either a new or existing child node
     */
    public function addChild(string $name, Migration|null $migration = null): MigrationNode
    {
        if ($this->isLeaf())
            throw new RuntimeException("cannot add child '$name' to a leaf node");

        if (!($child = $this->children[$name] ?? false))
            $child = $this->children[$name] = new self($name, $migration);

        return $child;
    }

    public function isLeaf(): bool
    {
        return $this->migration !== null;
    }

    public function sort(SortPolicy $sortPolicy): self
    {
        $strcmp = $sortPolicy->isNatural() ? strnatcmp(...) : strcmp(...);

        uasort($this->children, static function (self $a, self $b) use ($sortPolicy, $strcmp) {
            /* directories first */
            if ($n = ($a->isLeaf() - $b->isLeaf()))
                return $n;

            if ($sortPolicy->isCaseInsensitive()) {
                $a = mb_convert_case($a->name, MB_CASE_FOLD, 'UTF-8');
                $b = mb_convert_case($b->name, MB_CASE_FOLD, 'UTF-8');
            }

            return $strcmp($a->name, $b->name);
        });

        /* sort grandchildren */
        foreach ($this->children as $child)
            $child->sort($sortPolicy);

        return $this;
    }

    /** Converts tree to a depth first array of migrations (flatten).
     * @return Migration[]
     */
    public function toMigrations(): array
    {
        $migrations = [];

        foreach ($this->children as $child) {
            if ($child->isLeaf())
                $migrations[] = $child->migration;
            else
                array_push($migrations, ...$child->toMigrations());
        }

        return $migrations;
    }
}