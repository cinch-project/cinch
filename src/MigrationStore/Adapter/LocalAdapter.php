<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\MigrationStore\Directory;
use Cinch\Common\Dsn;
use Cinch\Common\Location;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class LocalAdapter extends MigrationStoreAdapter
{
    public static function fromDsn(Dsn $dsn, string $defaultBaseDir): self
    {
        Assert::equals($dsn->getScheme(), 'file', "expected file dsn");

        $dir = $dsn->getPath();

        if (Path::isRelative($dir)) {
            Assert::notEmpty($defaultBaseDir, 'migration store requires baseDir for relative URIs');
            if (Path::isRelative($defaultBaseDir))
                throw new AssertException("baseDir must be absolute, found '$defaultBaseDir'");
            $dir = Path::makeAbsolute($dir, $defaultBaseDir);
        }

        return new self(Assert::directory($dir, 'migration store directory'));
    }

    public function search(Directory $dir): array
    {
        $finder = (new Finder)
            ->in($this->resolvePath($dir->path))
            ->name(self::FILENAME_PATTERN)
            ->ignoreDotFiles(true)
            ->notPath($dir->exclude)
            ->files();

        if (!($dir->flags & Directory::RECURSIVE))
            $finder->depth(0);

        if ($dir->flags & Directory::FOLLOW_LINKS)
            $finder->followLinks();

        $files = [];

        /** on first iteration, finder throws DirectoryNotFoundException if $dir does not exist.
         * @var SplFileInfo $file
         */
        foreach ($finder as $file) {
            $location = new Location(Path::makeRelative($file->getRealPath(), $this->storeDir));
            $files[] = new LocalFile($file->getRealPath(), $location);
        }

        return $files;
    }

    /**
     * @throws Exception
     */
    public function addFile(string $path, string $content, string $message): FileId
    {
        $path = $this->resolvePath($path);

        if (file_exists($path))
            throw new Exception("$path already exists: message=$message");

        if (@file_put_contents($path, $content) === false)
            throw_last_error();

        return new FileId;
    }

    public function deleteFile(string $path, string $message, FileId $fileId): void
    {
        (new Filesystem())->remove($this->resolvePath($path));
    }

    protected function getContentsByPath(string $path): string
    {
        $path = $this->resolvePath($path);
        if (!file_exists($path))
            throw new FileNotFoundException(path: $path);
        return slurp($path);
    }
}