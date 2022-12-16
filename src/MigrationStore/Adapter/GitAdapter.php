<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

abstract class GitAdapter extends MigrationStoreAdapter
{
    const TOKEN_ENV_NAME = '';

    protected readonly Client $client;
    private readonly int $storeDirLen;
    private readonly string $messagePrefix;

    /**
     * @param string $baseUri should include owner, project, repo, API version, etc.
     * @param string $storeDir this is the relative path from the root of the repo, unlike the local
     * adapter which must be absolute. Example: $repo/database/cinch_sales is "database/cinch_sales".
     * @param string $branch
     * @param array $config guzzle config
     */
    public function __construct(
        protected readonly string $baseUri,
        protected readonly string $branch,
        string $storeDir,
        array $config)
    {
        /* this should always be present */
        $userAgent = array_change_key_case($config['headers'])['user-agent'] ?? 'cinch';
        $this->messagePrefix = str_replace('/', '-', $userAgent);

        $this->client = new Client($config);
        parent::__construct(trim($storeDir, '/'));

        /* when listing trees, blob paths come back as storeDir/dir/blob.php. cinch Locations are
         * relative to the migration store dir. Thus, we chop off storeDirLen chars from blob path.
         */
        if (($len = strlen($this->storeDir)))
            $len++; // +1 for '/' after storeDir

        $this->storeDirLen = $len;
    }

    /**
     * @throws Exception
     */
    public abstract static function fromDsn(Dsn $dsn, string $userAgent): static;

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

    /** Converts tree entry objects to File objects. The "*Key" parameters exist since each Git provider
     * uses different names, although the values are the same.
     * @param array $tree array of tree entry objects
     * @param array $exclude paths to exclude using regex patterns
     * @param string $pathKey key to get the entry path
     * @param string $typeKey key to get the entry type
     * @param string $shaKey key to get the entry sha-1
     * @return File[]
     */
    protected function toFiles(array $tree, array $exclude, string $pathKey, string $typeKey, string $shaKey): array
    {
        $files = [];

        foreach ($tree as $entry) {
            /* make relative to storeDir */
            $path = substr(ltrim(Path::normalize($entry[$pathKey]), '/'), $this->storeDirLen);

            if ($this->accept($path, $entry[$typeKey], $exclude))
                $files[] = new GitFile($this, new StorePath($path), new Checksum($entry[$shaKey]));
        }

        return $files;
    }

    /** Accepts or rejects a migration script.
     * @param string $path
     * @param string $type
     * @param array $exclude
     * @return bool
     */
    protected function accept(string $path, string $type, array $exclude): bool
    {
        $name = basename($path);

        if ($type != 'blob' || $name[0] == '.' || !preg_match(self::FILENAME_PATTERN, $name))
            return false;

        /* store.yml 'exclude: [pattern, ...]' */
        foreach ($exclude as $pattern)
            if (preg_match($pattern, $path) === 1)
                return false;

        return true;
    }

    /** Gets the guzzle/http SSL config options.
     * @param Dsn $dsn
     * @return array
     */
    protected static function getSslConfig(Dsn $dsn): array
    {
        $config = ['verify' => $dsn->getFile('sslca', true)];

        if ($value = $dsn->getFile('sslcert'))
            $config['cert'] = $value;

        if ($value = $dsn->getFile('sslkey'))
            $config['ssl_key'] = $value;

        return $config;
    }

    protected static function getToken(Dsn $dsn): string
    {
        $token = $dsn->getOption('token', getenv(static::TOKEN_ENV_NAME) ?: '');
        $name = str_replace('Adapter', '', classname(static::class));
        return Assert::that($token, "$name token")->string()->notEmpty()->value();
    }
}