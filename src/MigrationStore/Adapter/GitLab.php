<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\MigrationStore\File;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class GitLab extends Git
{
    const TOKEN_ENV_NAME = 'CINCH_GITLAB_TOKEN';

    /**
     * @param int $flags
     * @throws GuzzleException|Exception
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
        $uri = "$this->baseUri/tree?" . http_build_query($query);

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

        $this->client->post("$this->baseUri/files/$path", [
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

        $this->client->delete("$this->baseUri/files/$path", [
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

        $data = $this->getFileByUri("$this->baseUri/files/$path?ref=$this->branch");
        return new File($this, new StorePath($path), new Checksum($data['blob_id']), base64_decode($data['content']));
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(string $path): string
    {
        if (!($path = rawurlencode($this->resolvePath($path))))
            throw new RuntimeException("cannot get file contents without a path");
        return $this->getContentsByUri("$this->baseUri/files/$path/raw?ref=$this->branch");
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(Dsn $dsn, string $userAgent): static
    {
        // gitlab://host:port/<project_id>/store_dir?branch=master&token=token

        Assert::equals($dsn->getScheme(), 'gitlab', "expected gitlab dsn");

        $host = Assert::hostOrIp($dsn->getHost() ?: 'gitlab.com', 'gitlab host');
        $port = Assert::int($dsn->getPort() ?? 443, 'gitlab port');

        $parts = explode('/', trim($dsn->getPath(), '/'), 2);
        $projectId = (int) Assert::that(array_shift($parts), 'gitlab project ID')
            ->digit()->greaterThanEqualTo(1)->value();
        $storeDir = array_shift($parts) ?: '';

        $branch = Assert::notEmpty($dsn->getOption('branch'), 'gitlab branch');
        $baseUri = "/api/v4/projects/$projectId/repository";

        return new static($baseUri, $branch, $storeDir, [
            'base_uri' => "https://$host:$port",
            'connect_timeout' => $dsn->getConnectTimeout(),
            'read_timeout' => $dsn->getTimeout() / 1000,
            'headers' => [
                'User-Agent' => $userAgent,
                'Authorization' => 'Bearer ' . self::getToken($dsn) // personal, group, project access token
            ],
            ...self::getSslConfig($dsn)
        ]);
    }
}