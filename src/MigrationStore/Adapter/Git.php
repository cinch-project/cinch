<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\Adapter;
use Cinch\MigrationStore\File;
use Cinch\MigrationStore\StoreDsn;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

abstract class Git extends Adapter
{
    const TOKEN_ENV_NAME = '';

    protected readonly Client $client;
    private readonly string $messagePrefix;

    /**
     * @param string $basePath should include owner, project, repo, API version, etc.
     * @param string $storeDir this is the relative path from the root of the repo, unlike the local
     * adapter which must be absolute. Example: $repo/database/cinch_sales is "database/cinch_sales".
     * @param string $branch
     * @param array $config guzzle config
     */
    public function __construct(
        protected readonly string $basePath,
        protected readonly string $branch,
        string $storeDir,
        array $config)
    {
        /* this should always be present */
        $userAgent = array_change_key_case($config['headers'])['user-agent'] ?? 'cinch';
        $this->messagePrefix = str_replace('/', '-', $userAgent);

        $this->client = new Client($config);
        parent::__construct(trim($storeDir, '/'));
    }

    /**
     * @throws GuzzleException
     */
    protected function getFileByUri(string|Uri $uri, array $options = []): array
    {
        try {
            return $this->toJson($this->client->get($uri, $options));
        }
        catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 404)
                throw new FileNotFoundException(path: $uri);
            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     */
    protected function getContentsByUri(string|Uri $uri, array $options = []): string
    {
        try {
            return $this->client->get($uri, $options)->getBody()->getContents();
        }
        catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 404)
                throw new FileNotFoundException(path: $uri);
            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     */
    protected function getTree(string|Uri $uri): array
    {
        try {
            return $this->toJson($this->client->get($uri));
        }
        catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 404)
                throw new DirectoryNotFoundException("The \"$uri\" directory does not exist");
            throw $e;
        }
    }

    /**
     * @throws AssertException
     */
    protected function toJson(ResponseInterface $r): array
    {
        Assert::contains($r->getHeaderLine('content-type'), 'application/json', message: 'content-type');
        return json_decode($r->getBody()->getContents(), associative: true);
    }

    protected function buildCommitMessage(string $message): string
    {
        return "$this->messagePrefix: " . Assert::notEmpty($message, 'commit message');
    }

    /** Converts a tree into file objects. The "*Key" parameters exist since each Git provider
     * uses different names, although the values are the same.
     * @param array $tree array of tree entry objects
     * @param string $pathKey key to get the entry path
     * @param string $typeKey key to get the entry type
     * @param string $shaKey key to get the entry sha-1
     * @param bool $relativeToRoot does provider returns paths relative to repo root
     * @return File[]
     */
    protected function getFilesFromTree(array $tree, string $pathKey, string $typeKey,
        string $shaKey, bool $relativeToRoot = true): array
    {
        /* most providers return paths relative to repo root: $storeDir/dir/blob.php. */
        if ($storeDirLength = strlen($this->storeDir))
            $storeDirLength++; // +1 for '/' after storeDir

        $files = [];

        foreach ($tree as $e) {
            $name = basename($path = trim($e[$pathKey], '/'));

            if ($e[$typeKey] != 'blob' || !preg_match(self::FILE_PATTERN, $name))
                continue;

            if ($relativeToRoot)
                $path = substr($path, $storeDirLength);

            $files[] = new File($this, new StorePath($path), new Checksum($e[$shaKey]));
        }

        return $files;
    }

    public static function fromDsn(StoreDsn $dsn, string $userAgent): static
    {
        return new static($dsn->basePath, $dsn->branch, $dsn->storeDir, [
            'base_uri' => $dsn->baseUri,
            'connect_timeout' => $dsn->connectTimeout,
            'read_timeout' => $dsn->timeout / 1000,
            'headers' => [
                'User-Agent' => $userAgent,
                'Authorization' => $dsn->getAuthorization()
            ], ...self::getSslConfig($dsn)
        ]);
    }

    /** Gets the guzzle/http SSL config options.
     * @param Dsn $dsn
     * @return array
     */
    private static function getSslConfig(Dsn $dsn): array
    {
        $config = ['verify' => $dsn->sslca ?? true];

        if ($value = $dsn->sslcert)
            $config['cert'] = $value;

        if ($value = $dsn->sslkey)
            $config['ssl_key'] = $value;

        return $config;
    }
}