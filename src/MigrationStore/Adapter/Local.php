<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Cinch\LastErrorException;
use Cinch\MigrationStore\Adapter;
use Cinch\MigrationStore\Directory;
use Cinch\MigrationStore\File;
use Cinch\MigrationStore\LocalFile;
use Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Local extends Adapter
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

        /**
         * @var SplFileInfo $file
         */
        foreach ($finder as $file) {
            $path = new StorePath(Path::makeRelative($file->getRealPath(), $this->storeDir));
            $files[] = new LocalFile($file->getRealPath(), $path);
        }

        return $files;
    }

    /**
     * @throws Exception
     */
    public function addFile(string $path, string $content, string $message): void
    {
        $path = $this->resolvePath($path);

        if (file_exists($path))
            throw new Exception("$path already exists: message=$message");

        if (@file_put_contents($path, $content) === false)
            throw new LastErrorException();
    }

    public function deleteFile(string $path, string $message): void
    {
        (new Filesystem())->remove($this->resolvePath($path));
    }

    public function getFile(string $path): File
    {
        return new LocalFile($this->resolvePath($path), new StorePath($path));
    }

    public function getContents(string $path): string
    {
        $path = $this->resolvePath($path);
        if (!file_exists($path))
            throw new FileNotFoundException(path: $path);
        return slurp($path);
    }
}