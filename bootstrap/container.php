<?php

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Dotenv\Dotenv;

try {
    $input = require 'peek-input.php';
    $datetime = (new DateTime(timezone: new DateTimeZone($input->timeZone)))->format('YmdHisO');
    $filename = "$input->command-$datetime.log";
    $logFile = $input->command == 'create' ? sys_get_temp_dir() . "/$filename" : "$input->projectDir/log/$filename";

    /* Use .env.local if it exists, since build-cli.php only bundles .env.prod in phar. */
    $dotEnv = (new Dotenv())->usePutenv();
    $env = file_exists('../.env.local') ? 'local' : 'prod';
    $dotEnv->populate(['CINCH_ENV' => $env], overrideExistingVars: true);
    $dotEnv->load(__DIR__ . "/../.env.$env");

    $resourceDir = dirname(__DIR__) . '/resources';

    $container = new ContainerBuilder();
    $container->setParameter('cinch.version', getenv('CINCH_VERSION'));
    $container->setParameter('cinch.resource_dir', $resourceDir);
    $container->setParameter('schema.version', getenv('CINCH_SCHEMA_VERSION'));
    $container->setParameter('schema.description', getenv('CINCH_SCHEMA_DESCRIPTION'));
    $container->setParameter('schema.release_date', getenv('CINCH_SCHEMA_RELEASE_DATE'));
    $container->setParameter('twig.auto_reload', $env != 'prod');
    $container->setParameter('twig.debug', getenv('CINCH_DEBUG') === '1');
    $container->setParameter('twig.template_dir', $resourceDir);
    $container->setParameter('project.dir', $input->projectDir);
    $container->setParameter('project.name', $input->projectDir ? basename($input->projectDir) : 'cinch');
    $container->setParameter('env_name', $input->envName);
    $container->setParameter('log.file', $logFile);
    $container->setParameter('log.time_zone', $input->timeZone);
    $container->setParameter('log.enabled', match ($input->command) {
        'history', 'check', 'pending', '' => false,
        default => true
    });

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
    $loader->load('services.yml');
    $container->compile();
    return $container;
}
catch (Exception $e) {
    fprintf(STDERR, "%s: %s in %s:%d\n%s\n", get_class($e), $e->getMessage(), $e->getFile(),
        $e->getLine(), $e->getTraceAsString());
    exit(1);
}