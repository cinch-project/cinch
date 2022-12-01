<?php

namespace Cinch\MigrationStore;

use Cinch\MigrationStore\Adapter\FileId;
use Cinch\MigrationStore\Adapter\MigrationStoreAdapter;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;
use Generator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;

class MigrationStore
{
    /** @var Directory[] */
    private array|null $directories = null;

    /* track ID for deletes (rollbacks). This is really designed for remote stores where an ID is needed for
     * deletes (avoids expensive REST calls). Not all adapters need this, in which case the ID is empty.
     */
    private FileId|null $storeId = null;

    public function __construct(
        private readonly MigrationStoreAdapter $storeAdapter,
        private readonly ScriptLoader $scriptLoader,
        private readonly string $resourceDir,
        private readonly MigrationFactory $migrationFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        if (!$this->exists())
            $this->storeId = $this->storeAdapter->addFile(
                MigrationStoreAdapter::FILENAME,
                slurp(Path::join($this->resourceDir, MigrationStoreAdapter::FILENAME)),
                'created ' . MigrationStoreAdapter::FILENAME
            );
    }

    /** Deletes the store file, not the directory. Only deletes if created with MigrationStore::create().
     * @return void
     */
    public function delete(): void
    {
        /* only delete if cinch created it, existing store.yml files are never deleted. */
        if ($this->storeId !== null)
            $this->storeAdapter->deleteFile(
                MigrationStoreAdapter::FILENAME,
                'deleted ' . MigrationStoreAdapter::FILENAME,
                $this->storeId
            );
    }

    /** Gets the next migration.
     * @return Generator<Migration>
     * @throws Exception
     */
    public function next(): Generator
    {
        if (!$this->exists())
            throw new Exception(MigrationStoreAdapter::FILENAME . ' does not exist');

        foreach ($this->directories as $dir)
            foreach ($dir->search($this->scriptLoader, $this->migrationFactory) as $migration)
                yield $migration;

        unset($this->directories);
    }

    /**
     * @throws Exception
     */
    private function exists(): bool
    {
        try {
            if ($this->directories === null)
                $this->directories = $this->storeAdapter->getDirectories();
            return true;
        }
        catch (FileNotFoundException) {
            return false;
        }
    }
}