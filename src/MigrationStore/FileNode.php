<?php

namespace Cinch\MigrationStore;

use Cinch\MigrationStore\Adapter\File;
use RuntimeException;

/** Used to build a tree of migration store directory files for the purpose of sorting.
 *
 * Due to remote data stores like github, azure, etc., listing one directory at a time is not practical:
 * API calls are typically between ~250ms and 500ms. Thus, when recursive, MigrationStoreAdapter::search()
 * returns a list that is unordered, multi-depth, and across many directories. This class builds a
 * tree, allowing us to walk it and sort one depth at a time. Trying to sort without a tree, using
 * quicksort and/or manual methods, proved to be complicated and ultimately elusive.
 *
 * @internal
 */
class FileNode
{
    /** @var FileNode[] */
    private array $children = [];

    public static function root(): self
    {
        return new self('', null);
    }

    private function __construct(private readonly string $name, private readonly File|null $file)
    {
    }

    /** Adds a child to this node.
     * @param string $name
     * @param File|null $file
     * @return FileNode either a new or existing child node
     */
    public function addChild(string $name, File|null $file = null): FileNode
    {
        if ($this->isLeaf())
            throw new RuntimeException("cannot add child '$name' to a leaf node");

        if (!($child = $this->children[$name] ?? false))
            $child = $this->children[$name] = new self($name, $file);

        return $child;
    }

    public function isLeaf(): bool
    {
        return $this->file !== null;
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

            return $strcmp($a, $b);
        });

        /* sort grandchildren */
        foreach ($this->children as $child)
            $child->sort($sortPolicy);

        return $this;
    }

    /** Converts tree to a depth first array of Files (flatten).
     * @return File[]
     */
    public function toFiles(): array
    {
        $files = [];

        foreach ($this->children as $child) {
            if ($child->isLeaf())
                $files[] = $child->file;
            else
                array_push($files, ...$child->toFiles());
        }

        return $files;
    }
}