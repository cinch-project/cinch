<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\MigrationStore\Directory;
use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class GitLabAdapter extends GitAdapter
{
    const TOKEN_ENV_NAME = 'CINCH_GITLAB_TOKEN';

    /**
     * @param Directory $dir
     * @return array
     * @throws GuzzleException|Exception
     */
    public function search(Directory $dir): array
    {
        $path = $this->resolvePath($dir->path);

        $query = [
            'path' => $path,
            'per_page' => 100,
            'pagination' => 'keyset',
            'order_by' => 'id',
            'sort' => 'asc',
            'ref' => $this->branch
        ];

        if ($dir->flags & Directory::RECURSIVE)
            $query['recursive'] = true;

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

        /* empty dirs are not allowed in git. if you request a tree from a non-existent dir, gitlab returns
         * an empty array rather than a 404. This is workable, but a 404 would be better.
         */
        if (!$tree)
            throw new DirectoryNotFoundException("The \"$path\" directory does not exist");

        return $this->toFiles($tree, $dir->exclude, 'path', 'type', 'id');
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): FileId
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

        return new FileId;
    }

    /**
     * @throws GuzzleException
     */
    public function deleteFile(string $path, string $message, FileId $fileId): void
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
    public function getContentsBySha(string $sha): string
    {
        return $this->getContentsByUri("$this->baseUri/blobs/$sha/raw");
    }

    /**
     * @throws GuzzleException
     */
    protected function getContentsByPath(string $path): string
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