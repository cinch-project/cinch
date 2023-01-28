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

class GitHub extends Git
{
    public const RAW = 'application/vnd.github.raw';
    public const JSON = 'application/vnd.github+json';

    /**
     * @param int $flags
     * @return array
     * @throws GuzzleException
     */
    public function getFiles(int $flags = 0): array
    {
        $encodedPath = rawurlencode($this->storeDir);
        $ref = $encodedPath ? "$this->branch:$encodedPath" : $this->branch; // extended SHA-1 syntax ... <ref>:<path>
        $tree = $this->getTree("$this->basePath/git/trees/$ref?recursive=1");

        /* GitHub sets this to 7MB or 100,000 entries. However: after calculating the approx (small) size of an
         * entry (200 bytes), only ~36,000 entries can fit within 7MB? Should be plenty for cinch though.
         */
        if (($tree['truncated'] ?? false) === true)
            throw new RuntimeException(sprintf('searching %s exceeded GitHub limits (%d entries)',
                $this->storeDir,
                count($tree['tree']),
            ));

        /* note: GitHub returns relative to storeDir (search path), not repo root */
        return $this->getFilesFromTree($tree['tree'], pathKey: 'path', typeKey: 'type', shaKey: 'sha', relativeToRoot: false);
    }

    /**
     * @throws GuzzleException
     */
    public function addFile(string $path, string $content, string $message): void
    {
        $this->client->put("$this->basePath/contents/{$this->resolvePath($path)}", [
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
        $this->client->delete("$this->basePath/contents/{$this->resolvePath($path)}", [
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

        $data = $this->getFileByUri("$this->basePath/contents/$path", [
            'query' => ['ref' => $this->branch]
        ]);

        /* contents api does not return data > 1M, documented and tested to return these values */
        if ($data['content'] == '' && $data['encoding'] == 'none')
            $content = null;
        else
            $content = base64_decode($data['content']); // use it if returned

        return new File($this, new StorePath($path), new Checksum($data['sha']), $content);
    }

    /**
     * @throws GuzzleException
     */
    public function getContents(string $path): string
    {
        if (!($path = $this->resolvePath($path)))
            throw new RuntimeException("cannot get contents without a path");

        /* note: github blocks files > 100M, plenty for cinch. */
        return $this->getContentsByUri("$this->basePath/contents/$path", [
            'headers' => ['Accept' => self::RAW],
            'query' => ['ref' => $this->branch]
        ]);
    }

    /**
     * @throws Exception
     */
    public static function fromDsn(StoreDsn $dsn, string $userAgent): static
    {
        Assert::equals($dsn->adapter, 'github', "expected github dsn");
        return parent::fromDsn($dsn, $userAgent);
    }
}
