<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class StoreDsn extends Dsn
{
    private const GITHUB_BASE_URI = 'https://api.github.com';
    private const AZURE_BASE_URI = 'https://dev.azure.com';
    private const GITLAB_HOST = 'gitlab.com';
    private const GITLAB_PORT = 443;

    public readonly string|null $baseUri;
    public readonly string|null $basePath;
    public readonly string|null $storeDir;
    public readonly string|null $branch;
    public readonly string|null $token;
    protected array $hidden = ['token'];

    public function getAuthorization(): string
    {
        return match ($this->driver) {
            // user:token, don't need user but colon must be present
            'azure' => 'Basic ' . base64_encode(':' . $this->token),
            'github', 'gitlab' => "Bearer $this->token",
            default => ''
        };
    }

    protected function setParameters(array $params): void
    {
        parent::setParameters($params);

        $token = $params['token'] ?? null;
        $branch = $params['branch'] ?? null;
        $this->storeDir = Assert::thatKey($params, 'store_dir', 'store_dir')->notEmpty()->value();

        if ($this->driver == 'fs')
            $this->basePath = $this->baseUri = $this->token = $this->branch = null;
        else
            $this->branch = Assert::notEmpty($branch, "$this->driver branch");

        switch ($this->driver) {
            case 'fs':
                break;

            case 'github':
            {
                $this->baseUri = self::GITHUB_BASE_URI;
                $this->token = $token ?: Assert::notEmpty(getenv('CINCH_GITHUB_TOKEN') ?: '', 'github token');
                $this->basePath = sprintf('/repos/%s/%s',
                    Assert::thatKey($params, 'owner', 'owner')->notEmpty()->value(),
                    Assert::thatKey($params, 'repo', 'repo')->notEmpty()->value());
                break;
            }

            case 'gitlab':
            {
                $this->baseUri = sprintf('https://%s:%d', $params['host'] ?? self::GITLAB_HOST, $params['port'] ?? self::GITLAB_PORT);
                $this->token = $token ?: Assert::notEmpty(getenv('CINCH_GITLAB_TOKEN') ?: '', 'gitlab token');
                $this->basePath = sprintf('/api/v4/projects/%s/repository',
                    Assert::thatKey($params, 'project_id', 'project_id')->xdigit()->value());
                break;
            }

            case 'azure':
            {
                $this->baseUri = self::AZURE_BASE_URI;
                $this->token = $token ?: Assert::notEmpty(getenv('CINCH_AZURE_TOKEN') ?: '', 'azure token');
                $this->basePath = sprintf('/%s/%s/_apis/git/repositories/%s',
                    rawurlencode(Assert::thatKey($params, 'org', 'org')->notEmpty()->value()),
                    rawurlencode(Assert::thatKey($params, 'project', 'project')->notEmpty()->value()),
                    rawurlencode(Assert::thatKey($params, 'repo', 'repo')->notEmpty()->value()));
                break;
            }

            default:
                throw new AssertException("unknown store driver '$this->driver'");
        }
    }

    protected function getParameters(): array
    {
        $params = parent::getParameters();

        if ($this->driver == 'fs')
            $params = ['driver' => 'fs', 'store_dir' => $params['store_dir']];
        else
            unset($params['basePath'], $params['baseUri']);

        return $params;
    }
}