<?php

use Doctum\Doctum;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in('src');

return new Doctum($iterator, [
    'title' => 'Cinch API',
    //'language' => 'en', // Could be 'fr'
    //'build_dir' => __DIR__ . '/build',
    //'cache_dir' => __DIR__ . '/cache',
    //'source_dir' => '/path/to/repository/',
    //'remote_repository' => new GitHubRemoteRepository('username/repository', '/path/to/repository'),
    //'default_opened_level' => 2, // optional, 2 is the default value
]);
