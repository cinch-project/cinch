<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Dsn;
use Cinch\Common\Location;
use Cinch\Component\Assert\Assert;
use Cinch\MigrationStore\Directory;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class GitHubAdapter extends GitAdapter
{
    const RAW = 'application/vnd.github.raw';
    const JSON = 'application/vnd.github+json';
    const TOKEN_ENV_NAME = 'CINCH_GITHUB_TOKEN';

    /**
     * @throws GuzzleException
     */
    public function search(Directory $dir): array
    {
        $dirPath = $this->resolvePath($dir->path);
        $encodedPath = rawurlencode($dirPath);
        $ref = $encodedPath ? "$this->branch:$encodedPath" : $this->branch; // extended SHA-1 syntax ... <ref>:<path>

        $recursive = $dir->flags & Directory::RECURSIVE;
        $uri = "$this->baseUri/git/trees/$ref" . ($recursive ? '?recursive=1' : '');

        $tree = $this->getTree($uri);

        /* GitHub sets this to 7MB or 100,000 entries. However: after calculating the approx (small) size of an
         * entry (200 bytes), only ~36,000 entries can fit within 7MB? Should be plenty for cinch though.
         */
        if ($tree['truncated'] ?? false === true)
            throw new RuntimeException(sprintf('searching %s exceeded GitHub limits (%d entries retrieved %s)',
                $dirPath,
                count($tree['tree']),
                $recursive ? 'recursively' : 'non-recursively'
            ));

        return $this->toFiles($tree['tree'], $dir->exclude, 'path', 'type', 'sha');
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): void
    {
        $this->client->put("$this->baseUri/contents/{$this->resolvePath($path)}", [
            'json' => [
                'message' => $this->buildCommitMessage($message),
                'branch' => $this->branch,
                'content' => base64_encode($content)
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function deleteFile(string $path, string $message): void
    {
        $this->client->delete("$this->baseUri/contents/{$this->resolvePath($path)}", [
            'json' => [
                'message' => $this->buildCommitMessage($message),
                'branch' => $this->branch,
                'sha' => $this->getFile($path)->getChecksum()->value // github requires blob sha
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getFile(string $path): File
    {
        if (!($path = $this->resolvePath($path)))
            throw new RuntimeException("cannot get file without a path");

        $data = $this->getFileByUri("$this->baseUri/contents/$path", [
            'query' => ['ref' => $this->branch]
        ]);

        /* contents api does not return data > 1M, documented and tested to return these values */
        if ($data['content'] == '' && $data['encoding'] == 'none')
            $content = null;
        else
            $content = base64_decode($data['content']); // use it if returned

        return new GitFile($this, new Location($path), new Checksum($data['sha']), $content);
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(string $path): string
    {
        if (!($path = $this->resolvePath($path)))
            throw new RuntimeException("cannot get contents without a path");

        /* github blocks files > 100M, plenty for cinch. Must use Git Large File Storage (Git LFS) */
        return $this->getContentsByUri("$this->baseUri/contents/$path", [
            'headers' => ['Accept' => self::RAW],
            'query' => ['ref' => $this->branch]
        ]);
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(Dsn $dsn, string $userAgent): static
    {
        // github:owner/repo/storeDir?branch=branch&token=token

        Assert::equals($dsn->getScheme(), 'github', "expected github dsn");
        Assert::empty($dsn->getHost(), 'github host not supported');

        $parts = explode('/', trim($dsn->getPath(), '/'), 3);
        $owner = Assert::notEmpty(array_shift($parts), 'github owner');
        $repo = Assert::notEmpty(array_shift($parts), 'github repo');
        $storeDir = array_shift($parts) ?: '';

        $branch = Assert::notEmpty($dsn->getOption('branch'), 'github branch');

        return new static("/repos/$owner/$repo", $branch, $storeDir, [
            'base_uri' => 'https://api.github.com',
            'connect_timeout' => $dsn->getConnectTimeout(),
            'read_timeout' => $dsn->getTimeout() / 1000,
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept' => self::JSON,
                'Authorization' => 'Bearer ' . self::getToken($dsn)
            ], ...self::getSslConfig($dsn)
        ]);
    }
}