<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\MigrationStore\Directory;
use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/** This supports Azure DevOps Services (cloud), not Azure DevOps Server (on-premise). */
class AzureAdapter extends GitAdapter
{
    const TOKEN_ENV_NAME = 'CINCH_AZURE_TOKEN';

    /* docs say set to 6.0 but response headers return 7.0 -- Nov 9, 2022 */
    private const API_VERSION = '7.0';
    private readonly array $queryVersion;

    public function __construct(string $baseUri, string $branch, string $storeDir, array $config)
    {
        parent::__construct($baseUri, $branch, $storeDir, $config);
        $this->queryVersion = [
            'searchCriteria.compareVersion.version' => $this->branch,
            'searchCriteria.compareVersion.versionType' => 'branch'
        ];
    }

    /**
     * @throws GuzzleException
     */
    public function search(Directory $dir): array
    {
        $query = http_build_query([
            'api-version' => self::API_VERSION,
            'scopePath' => $this->resolvePath($dir->path),
            'recursionLevel' => ($dir->flags & Directory::RECURSIVE) ? 'full' : 'oneLevel',
            ...$this->queryVersion
        ]);

        $tree = $this->getTree("$this->baseUri/items?$query");
        return $this->toFiles($tree['values'], $dir->exclude, 'path', 'gitObjectType', 'objectId');
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): FileId
    {
        $r = $this->client->post("$this->baseUri/pushes?api-version=" . self::API_VERSION, [
            'json' => [
                "refUpdates" => [
                    [
                        "name" => "refs/heads/$this->branch",
                        "oldObjectId" => $this->getLastCommitId()
                    ]
                ],
                'commits' => [
                    [
                        'comment' => $this->buildCommitMessage($message),
                        'changes' => [
                            [
                                'changeType' => 'add',
                                'item' => ['path' => $this->resolvePath($path)],
                                'newContent' => [
                                    'content' => base64_encode($content),
                                    'contentType' => 'base64encoded'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        return new FileId($this->toJson($r)['commits'][0]['commitId']);
    }

    /**
     * @throws GuzzleException
     */
    public function deleteFile(string $path, string $message, FileId $fileId): void
    {
        $this->client->post("$this->baseUri/pushes?api-version=" . self::API_VERSION, [
            'json' => [
                "refUpdates" => [
                    [
                        "name" => "refs/heads/$this->branch",
                        "oldObjectId" => $fileId->value
                    ]
                ],
                'commits' => [
                    [
                        'comment' => $this->buildCommitMessage($message),
                        'changes' => [
                            [
                                'changeType' => 'delete',
                                'item' => ['path' => $this->resolvePath($path)]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getContentsBySha(string $sha): string
    {
        $query = http_build_query(['api-version' => self::API_VERSION, '$format' => 'text']);
        return $this->getContentsByUri("$this->baseUri/blobs/$sha?$query");
    }

    /**
     * @throws GuzzleException
     */
    protected function getContentsByPath(string $path): string
    {
        if (!($path = $this->resolvePath($path)))
            throw new RuntimeException("cannot get blob without a path");

        $query = http_build_query([
            'api-version' => self::API_VERSION,
            'path' => $path,
            '$format' => 'text',
            'includeContent' => true,
            'recursionLevel' => 'none',
            ...$this->queryVersion
        ]);

        return $this->getContentsByUri("$this->baseUri/items?$query");
    }

    /** unfortunately, azure requires latest commit id to add/delete a file. separate call required
     * @throws GuzzleException
     */
    private function getLastCommitId(): string
    {
        $query = http_build_query([
            'name' => $this->branch,
            'api-version' => self::API_VERSION
        ]);

        $stats = $this->toJson($this->client->get("$this->baseUri/stats/branches?$query"));
        return $stats['commit']['commitId'];
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(Dsn $dsn, string $userAgent): static
    {
        // azure:organization/<project>/<repo>/storeDir?branch=branch

        Assert::equals($dsn->getScheme(), 'azure', "expected azure dsn");
        Assert::empty($dsn->getHost(), 'gitlab host not supported');

        $parts = explode('/', trim($dsn->getPath(), '/'), 3);

        /* azure limits org name to ASCII letters and digits for the first and last character and allows
         * ASCII letters, digits and hyphen for middle characters.
         */
        $pattern = '~^(?:[a-z\d]|[a-z\d][a-z\d-]*[a-z\d])$~i';
        $org = Assert::regex(array_shift($parts), $pattern, 'azure organization');

        $project = Assert::notEmpty(array_shift($parts), 'azure project');
        $repo = Assert::notEmpty(array_shift($parts), 'azure repo');
        $storeDir = array_shift($parts) ?? '';

        $branch = Assert::notEmpty($dsn->getOption('branch'), 'azure branch');

        $baseUri = sprintf('%s/%s/_apis/git/repositories/%s',
            rawurlencode($org), rawurlencode($project), rawurlencode($repo));

        return new static($baseUri, $branch, $storeDir, [
            'base_uri' => "https://dev.azure.com",
            'connect_timeout' => $dsn->getConnectTimeout(),
            'read_timeout' => $dsn->getTimeout() / 1000,
            'headers' => [
                'User-Agent' => $userAgent,
                // user:token, don't need user but colon must be present
                'Authorization' => 'Basic ' . base64_encode(':' . self::getToken($dsn))
            ], ...self::getSslConfig($dsn)
        ]);
    }
}