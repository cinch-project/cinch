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

/** This supports Azure DevOps Services (cloud), not Azure DevOps Server (on-premise). */
class Azure extends Git
{
    private const API_VERSION = '7.0';
    private readonly array $branchInfo;

    public function __construct(string $basePath, string $branch, string $storeDir, array $config)
    {
        parent::__construct($basePath, $branch, $storeDir, $config);
        $this->branchInfo = [
            'searchCriteria.compareVersion.version' => $this->branch,
            'searchCriteria.compareVersion.versionType' => 'branch'
        ];
    }

    /**
     * @param int $flags
     * @return File[]
     * @throws GuzzleException
     */
    public function getFiles(int $flags = 0): array
    {
        $query = http_build_query([
            'api-version' => self::API_VERSION,
            'scopePath' => $this->storeDir,
            'recursionLevel' => 'full',
            ...$this->branchInfo
        ]);

        $tree = $this->getTree("$this->basePath/items?$query");
        return $this->getFilesFromTree($tree['values'], 'path', 'gitObjectType', 'objectId');
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): void
    {
        $this->client->post("$this->basePath/pushes?api-version=" . self::API_VERSION, [
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
    }

    /**
     * @throws GuzzleException
     */
    public function deleteFile(string $path, string $message): void
    {
        $this->client->post("$this->basePath/pushes?api-version=" . self::API_VERSION, [
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
    public function getFile(string $path): File
    {
        $query = $this->getItemsQueryString($path);
        $data = $this->getFileByUri("$this->basePath/items?$query", [
            'headers' => ['Accept' => 'application/json'] // metadata and content
        ]);

        return new File($this, new StorePath($path), new Checksum($data['objectId']), $data['content']);
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(string $path): string
    {
        $query = $this->getItemsQueryString($path);
        return $this->getContentsByUri("$this->basePath/items?$query", [
            'headers' => ['Accept' => 'text/plain'] // only content
        ]);
    }

    private function getItemsQueryString(string $path): string
    {
        if (!($path = $this->resolvePath($path)))
            throw new RuntimeException("cannot get items without a path");

        return http_build_query([
            'api-version' => self::API_VERSION,
            'path' => $path,
            'includeContent' => 'true',
            ...$this->branchInfo
        ]);
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

        $stats = $this->toJson($this->client->get("$this->basePath/stats/branches?$query"));
        return $stats['commit']['commitId'];
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(StoreDsn $dsn, string $userAgent): static
    {
        Assert::equals($dsn->driver, 'azure', "expected azure dsn");
        return parent::fromDsn($dsn, $userAgent);
    }
}