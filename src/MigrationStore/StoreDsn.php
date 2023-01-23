<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class StoreDsn extends Dsn
{
    public readonly string|null $baseUri;
    public readonly string|null $basePath;
    public readonly string|null $storeDir;
    public readonly string|null $branch;
    public readonly string|null $token;
    protected array $hidden = ['token'];

    /* These parameters are only used within string|array DSNs. They compose basePath and/or baseUri and don't need
     * to be accessible outside this class. They are needed in order to recreate a string|array DSN.
     */
    private array $parameters = ['org' => null, 'owner' => null, 'project' => null, 'project_id' => null,
        'repo' => null, 'host' => null, 'port' => null];

    public function getAuthorization(): string
    {
        return match ($this->adapter) {
            // azure expects user:token, we don't need user but the colon must still be present
            'azure' => 'Basic ' . base64_encode(':' . $this->token),
            'github', 'gitlab' => "Bearer $this->token",
            default => ''
        };
    }

    protected function setParameters(array $params): void
    {
        parent::setParameters($params);

        $setDriverParameters = 'set' . ucfirst($this->adapter) . 'Parameters';
        if (!method_exists($this, $setDriverParameters))
            throw new AssertException("unknown migration store adapter '$this->adapter'");

        /* all adapters require storeDir */
        $this->storeDir = Assert::thatKey($params, 'store_dir', "$this->adapter store_dir")->notEmpty()->value();

        /* all git adapters require branch */
        if ($this->adapter != 'fs')
            $this->branch = Assert::thatKey($params, 'branch', "$this->adapter branch")->notEmpty()->value();

        $this->$setDriverParameters($params);
    }

    protected function getParameters(): array
    {
        if ($this->adapter == 'fs')
            return ['adapter' => 'fs', 'store_dir' => $this->storeDir];

        $params = parent::getParameters();

        /* part of OO model only, these are composed of other parameters */
        unset($params['basePath'], $params['baseUri']);

        /* add adapter-specific parameters: if not used by adapter, they will be null */
        foreach ($this->parameters as $name => $value)
            if ($value !== null)
                $params[$name] = $value;

        return $params;
    }

    private function setFsParameters(array $params): void
    {
        $this->basePath = $this->baseUri = $this->token = $this->branch = null;
    }

    private function setGithubParameters(array $params): void
    {
        $this->baseUri = 'https://api.github.com';
        $this->token = $this->getToken($params, 'CINCH_GITHUB_TOKEN');
        $this->basePath = sprintf('/repos/%s/%s',
            $this->parameters['owner'] = Assert::thatKey($params, 'owner', 'github owner')->notEmpty()->value(),
            $this->parameters['repo'] = Assert::thatKey($params, 'repo', 'github repo')->notEmpty()->value());
    }

    private function setGitlabParameters(array $params): void
    {
        $this->parameters['host'] = Assert::ifKey($params, 'host', 'gitlab.com', 'gitlab host')->hostOrIp()->value();
        $this->parameters['port'] = (int) Assert::ifKey($params, 'port', 443, 'gitlab port')->between(1, 65535)->value();
        $this->baseUri = sprintf('https://%s:%s', $this->parameters['host'], $this->parameters['port']);
        $this->token = $this->getToken($params, 'CINCH_GITLAB_TOKEN');

        $this->parameters['project_id'] = (int) Assert::thatKey($params, 'project_id', 'gitlab project_id')->greaterThan(0)->value();
        $this->basePath = sprintf('/api/v4/projects/%d/repository', $this->parameters['project_id']);
    }

    private function setAzureParameters(array $params): void
    {
        $this->baseUri = 'https://dev.azure.com';
        $this->token = $this->getToken($params, 'CINCH_AZURE_TOKEN');
        $this->basePath = sprintf('/%s/%s/_apis/git/repositories/%s',
            rawurlencode($this->parameters['org'] = Assert::thatKey($params, 'org', 'azure org')->notEmpty()->value()),
            rawurlencode($this->parameters['project'] = Assert::thatKey($params, 'project', 'azure project')->notEmpty()->value()),
            rawurlencode($this->parameters['repo'] = Assert::thatKey($params, 'repo', 'azure repo')->notEmpty()->value()));
    }

    private function getToken(array $params, string $envName): string
    {
        return Assert::ifKey($params, 'token', getenv($envName) ?: '', "$this->adapter token")->notEmpty()->value();
    }
}