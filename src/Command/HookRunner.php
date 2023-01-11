<?php

namespace Cinch\Command;

use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\Deployment;
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

class HookRunner
{
    public function __construct(
        private readonly Deployment $deployment,
        private readonly Project $project,
        private readonly Session $target,
        private readonly LoggerInterface $logger,
        private readonly Twig $twig)
    {
    }

    /**
     * @param Event $event
     * @return Hook[]
     */
    public function getHooksForEvent(Event $event): array
    {
        return array_filter($this->project->getHooks(), fn($h) => in_array($event, $h->events));
    }

    /** Run all hooks registered with the given event.
     * @throws Exception
     */
    public function run(Event $event, Change|null $change = null): void
    {
        foreach ($this->getHooksForEvent($event) as $hook)
            $this->runHook($hook, $event, $change);
    }

    /**
     * @throws Exception
     */
    public function runHook(Hook $hook, Event $event, Change|null $change): void
    {
        if ($this->deployment->isDryRun())
            return;

        [$exitCode, $error, $timeout] = match ($hook->action->getType()) {
            ActionType::HTTP => $this->runHttpHook($hook, $event, $change),
            ActionType::SQL => $this->runSqlHook($hook, $event, $change),
            ActionType::PHP => $this->runPhpHook($hook, $event, $change),
            ActionType::SCRIPT => $this->runScriptHook($hook, $event, $change)
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
            $hook->abortOnError ? throw new Exception($message) : $this->logger->error($message);
    }

    private function runSqlHook(Hook $hook, Event $event, Change|null $change): array
    {
        try {
            $path = $hook->action->getPath();
            $sql = $this->twig->createTemplate(slurp($path), basename($path))->render([
                ...getenv(),
                ...$this->envVars($hook, $event, $change),
                ...$hook->action->getVariables() // uri query params -> "sql:/home/foo/a.sql?name=value"
            ]);

            $this->target->executeStatement($sql);
            return [0, '', false];
        }
        catch (Exception $e) {
            return [1, $e->getMessage(), false];
        }
    }

    private function runPhpHook(Hook $hook, Event $event, Change|null $change): array
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
                $hook, // hook.action.getVariables available - "php:/home/foo/a.php?name=value"
                $change,
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

    private function runScriptHook(Hook $hook, Event $event, Change|null $change): array
    {
        $error = '';
        $path = $hook->action->getPath();

        /* variables become ENV vars: my_var=1 -> CINCH_my_var=1 */
        $variables = [];
        foreach ($hook->action->getVariables() as $name => $value)
            $variables['CINCH_' . $name] = $value;

        $process = @proc_open(
            [$path, ...$hook->arguments],
            [2 => ['pipe', 'w']], // stderr
            $pipes,
            basename($path),
            [...getenv(), ...$this->envVars($hook, $event, $change), ...$variables],
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

        /* unfortunately, can't use stream_select() on pipes returned by proc_open, because it is
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

    private function runHttpHook(Hook $hook, Event $event, Change|null $change): array
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
            foreach ($this->envVars($hook, $event, $change) as $name => $value)
                $env[strtolower(substr($name, 6))] = $value; // remove CINCH_ prefix and lowercase

            /* variables contained within URL (hook.action) query params */
            (new Client)->post($hook->action->getPath(), [
                'timeout' => $hook->timeout,
                'headers' => $headers,
                'json' => $env // body -> {"hook_event": "before-once-migrate", "deployer": "foo", ...}
            ]);

            return [0, '', false];
        }
        catch (GuzzleException $e) {
            $message = $e->getMessage();
            return [1, $message, str_contains($message, 'Operation timed out')];
        }
    }

    private function envVars(Hook $hook, Event $event, Change|null $change): array
    {
        $env = [
            'CINCH_HOOK_EVENT' => $event->value,
            'CINCH_HOOK_TIMEOUT' => $hook->timeout,
            'CINCH_HOOK_ABORT_ON_ERROR' => $hook->abortOnError,
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

        if ($change) {
            $env['CINCH_CHANGE_PATH'] = $change->path->value;
            $env['CINCH_CHANGE_MIGRATE_POLICY'] = $change->migratePolicy->value;
            $env['CINCH_CHANGE_STATUS'] = $change->status->value;
            $env['CINCH_CHANGE_AUTHOR'] = $change->author->value;
            $env['CINCH_CHANGE_CHECKSUM'] = $change->checksum->value;
            $env['CINCH_CHANGE_DESCRIPTION'] = $change->description->value;
            $env['CINCH_CHANGE_LABELS'] = $change->labels->snapshot() ?? '';
            $env['CINCH_CHANGE_AUTHORED_AT'] = $change->authoredAt->format('Y-m-d\TH:i:s.uP');
            if ($event->isAfter())
                $env['CINCH_CHANGE_DEPLOYED_AT'] = $change->deployedAt->format('Y-m-d\TH:i:s.uP');
        }

        return $env;
    }
}