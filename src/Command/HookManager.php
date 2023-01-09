<?php

namespace Cinch\Command;

use Cinch\Database\Session;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\Hook\ActionType;
use Cinch\Hook\Event;
use Cinch\Hook\Handler as HookHandler;
use Cinch\Hook\HandlerContext;
use Cinch\Hook\Hook;
use Cinch\Project\Project;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Throwable;
use Twig\Environment as Twig;

class HookManager
{
    private readonly array $eventMap;

    public function __construct(
        private readonly Deployment $deployment,
        private readonly Project $project,
        private readonly Session $target,
        private readonly LoggerInterface $logger,
        private readonly Twig $twig)
    {
        $migrate = $this->deployment->getCommand() == DeploymentCommand::MIGRATE;

        $this->eventMap = [
            'before-deploy' => $migrate ? Event::BEFORE_MIGRATE : Event::AFTER_ROLLBACK,
            'before-each-deploy' => $migrate ? Event::BEFORE_EACH_MIGRATE : Event::AFTER_EACH_ROLLBACK,
            'after-deploy' => $migrate ? Event::AFTER_MIGRATE : Event::AFTER_ROLLBACK,
            'after-each-deploy' => $migrate ? Event::BEFORE_EACH_MIGRATE : Event::AFTER_EACH_ROLLBACK,
        ];
    }

    /**
     * @throws Exception
     */
    public function beforeConnect(): void
    {
        $this->invoke(Event::BEFORE_CONNECT);
    }

    /**
     * @throws Exception
     */
    public function afterConnect(): void
    {
        $this->invoke(Event::AFTER_CONNECT);
    }

    /**
     * @throws Exception
     */
    public function beforeDeploy(): void
    {
        $this->invoke('before-deploy');
    }

    /**
     * @throws Exception
     */
    public function afterDeploy(): void
    {
        $this->invoke('after-deploy');
    }

    /**
     * @throws Exception
     */
    private function invoke(string|Event $event): void
    {
        if (is_string($event))
            $event = $this->eventMap[$event];

        foreach (array_filter($this->project->getHooks(), fn($h) => in_array($event, $h->events)) as $hook) {
            [$exitCode, $error, $timeout] = match ($hook->action->getType()) {
                ActionType::HTTP => $this->invokeHttp($hook, $event),
                ActionType::SQL => $this->invokeSql($hook, $event),
                ActionType::PHP => $this->invokePhp($hook, $event),
                ActionType::SCRIPT => $this->invokeScript($hook, $event)
            };

            if ($timeout)
                $message = sprintf("%s hook '%s' timed out after %d seconds",
                    $event->value, $hook->action, $hook->timeout);
            else if ($exitCode)
                $message = sprintf("%s hook '%s' failed with exitcode %d%s",
                    $event->value, $hook->action, $exitCode, $error ? " - $error" : '');
            else
                $message = '';

            if ($message)
                $hook->failOnError ? throw new Exception($message) : $this->logger->error($message);
        }
    }

    private function invokeSql(Hook $hook, Event $event): array
    {
        try {
            $path = $hook->action->getPath();
            $sql = $this->twig->createTemplate(slurp($path), basename($path))->render([
                ...getenv(),
                ...$this->getEnvironment($hook, $event),
                ...$hook->action->getVariables()
            ]);

            $this->target->executeStatement($sql);
            return [0, '', false];
        }
        catch (Exception $e) {
            return [1, $e->getMessage(), false];
        }
    }

    private function invokePhp(Hook $hook, Event $event): array
    {
        try {
            $handler = require $hook->action->getPath();
        }
        catch (Throwable $e) {
            return [min(max($e->getCode(), 1), 255), $e->getMessage(), false];
        }

        if (!($handler instanceof HookHandler))
            return [1, "php handler must implement " . HookHandler::class, false];

        /* note: hook.timeout is the handler's responsibility */
        try {
            $error = '';
            $exitCode = $handler->handle($event, new HandlerContext(
                $hook,
                $this->target,
                $this->project->getId(),
                $this->project->getName(),
                $this->deployment->getTag(),
                $this->deployment->getCommand(),
                $this->deployment->getDeployer(),
                $this->deployment->getApplication(),
                $this->deployment->isDryRun(),
                $this->deployment->isSingleTransactionMode()
            ));
        }
        catch (Exception $e) {
            $error = $e->getMessage();
            $exitCode = min(max($e->getCode(), 1), 255);
        }

        return [$exitCode, $error, false];
    }

    private function invokeScript(Hook $hook, Event $event): array
    {
        $error = '';
        $path = $hook->action->getPath();

        $process = @proc_open(
            [$path, ...$hook->arguments],
            [2 => ['pipe', 'w']], // stderr
            $pipes,
            basename($path),
            [...getenv(), ...$this->getEnvironment($hook, $event)],
            ['bypass_shell']
        );

        if ($process === false)
            return [1, error_get_last()['message'], false];

        /* make non-blocking */
        $stderr = $pipes[2];
        stream_set_blocking($stderr, false);

        /* track runtime for timeouts */
        $timeout = false;
        $expiry = hrtime(true) + ($hook->timeout * 1e9);

        /* consume: unfortunately, can't use stream_select() on pipes returned by proc_open, because it is
         * completely broken on Windows [sighs]. Instead, manually poll with tiny sleeps.
         */
        while (true) {
            /* collect stderr output */
            if ($stderr) {
                if (feof($stderr)) {
                    fclose($stderr);
                    $stderr = null;
                }
                else if (($s = fread($stderr, 8192)) !== false) {
                    $error .= $s; // $s can be empty since $stderr is non-blocking
                }
            }

            /* poll */
            if (!proc_get_status($process)['running'])
                break;

            /* check timeout */
            if (hrtime(true) >= $expiry) {
                $timeout = true;
                break;
            }

            usleep(5 * 1000); // 5ms
        }

        if ($stderr)
            fclose($stderr);

        return [proc_close($process), $error, $timeout];
    }

    private function invokeHttp(Hook $hook, Event $event): array
    {
        try {
            $headers = [
                'content-type' => 'application/json',
                'user-agent' => $this->deployment->getApplication()
            ];

            foreach ($hook->headers as $name => $value)
                if (($name = strtolower($name)) != 'content-type')
                    $headers[$name] = $value;

            $env = [];
            foreach ($this->getEnvironment($hook, $event) as $name => $value)
                $env[strtolower(substr($name, 6))] = $value; // remove CINCH_ prefix and lowercase

            /* note: URL (hook.action) can contain ?query params */
            (new Client)->post($hook->action->getPath(), [
                'timeout' => $hook->timeout,
                'headers' => $headers,
                'json' => $env // body is {"hook_event": "before-migrate", "deployer": "foo", ...}
            ]);

            return [0, '', false];
        }
        catch (GuzzleException $e) {
            $message = $e->getMessage();
            return [1, $message, str_contains($message, 'Operation timed out')];
        }
    }

    private function getEnvironment(Hook $hook, Event $event): array
    {
        return [
            'CINCH_HOOK_EVENT' => $event->value,
            'CINCH_HOOK_TIMEOUT' => $hook->timeout,
            'CINCH_HOOK_FAIL_ON_ERROR' => $hook->failOnError,
            'CINCH_DEPLOYMENT_TAG' => $this->deployment->getTag()->value,
            'CINCH_DEPLOYMENT_COMMAND' => $this->deployment->getCommand()->value,
            'CINCH_DEPLOYER' => $this->deployment->getDeployer()->value,
            'CINCH_APPLICATION' => $this->deployment->getApplication(),
            'CINCH_TARGET_DSN' => $this->target->getPlatform()->getDsn()->toString(secure: false),
            'CINCH_DRY_RUN' => $this->deployment->isDryRun(),
            'CINCH_SINGLE_TRANSACTION' => $this->deployment->isSingleTransactionMode(),
            'CINCH_PROJECT_ID' => $this->project->getId()->value,
            'CINCH_PROJECT_NAME' => $this->project->getName()->value
        ];
    }
}