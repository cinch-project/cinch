<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\MigrationStore\File;
use Cinch\MigrationStore\StoreDsn;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class GitLab extends Git
{
    /**
     * @param int $flags
     * @return File[]
     * @throws GuzzleException
     */
    public function getFiles(int $flags = 0): array
    {
        $query = [
            'path' => $this->storeDir,
            'per_page' => 100,
            'ref' => $this->branch,
            'recursive' => true
        ];

        $tree = [];
        $uri = "$this->basePath/tree?" . http_build_query($query);

        while (true) {
            $r = $this->client->get($uri);

            if (!($link = $r->getHeader('Link')))
                break; // no more results

            array_push($tree, ...$this->toJson($r));

            /* '<https://...>; rel="next", <https://...>; rel="first", <https://...>; rel="last"' */
            if (preg_match('~<([^>]+)>;\s+rel="next"~', $link[0], $next) !== 1)
                break;

            $uri = $next[1];
        }

        /* gitlab returns an empty tree vs 404, as annoying as forcing pagination with tree api. */
        if (!$tree)
            throw new DirectoryNotFoundException("The \"$this->storeDir\" directory does not exist");

        return $this->getFilesFromTree($tree, 'path', 'type', 'id');
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): void
    {
        if (!($path = rawurlencode($this->resolvePath($path))))
            throw new RuntimeException("cannot add file without a path");

        $this->client->post("$this->basePath/files/$path", [
            'json' => [
                'branch' => $this->branch,
                'commit_message' => $this->buildCommitMessage($message),
                'encoding' => 'base64',
                'content' => base64_encode($content)
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function deleteFile(string $path, string $message): void
    {
        if (!($path = rawurlencode($this->resolvePath($path))))
            throw new RuntimeException("cannot delete file without a path");

        $this->client->delete("$this->basePath/files/$path", [
            'json' => [
                'branch' => $this->branch,
                'commit_message' => $this->buildCommitMessage($message)
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getFile(string $path): File
    {
        if (!($path = rawurlencode($this->resolvePath($path))))
            throw new RuntimeException("cannot get file without a path");

        $data = $this->getFileByUri("$this->basePath/files/$path?ref=$this->branch");
        return new File($this, new StorePath($path), new Checksum($data['blob_id']), base64_decode($data['content']));
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(string $path): string
    {
        if (!($path = rawurlencode($this->resolvePath($path))))
            throw new RuntimeException("cannot get file contents without a path");
        return $this->getContentsByUri("$this->basePath/files/$path/raw?ref=$this->branch");
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(StoreDsn $dsn, string $userAgent): static
    {
        Assert::equals($dsn->driver, 'gitlab', "expected gitlab dsn");
        return parent::fromDsn($dsn, $userAgent);
    }
}