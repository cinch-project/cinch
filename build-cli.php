<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

const INCLUDE_DIRS = ['config', 'resources', 'src', 'vendor'];
const INCLUDE_FILES = ['.env.prod', 'cinch', 'LICENSE'];
const BUILD_DIR = 'build';

(new Dotenv())->usePutenv()->load('.env.prod');
if (!($version = getenv('CINCH_VERSION')))
    throw new RuntimeException("missing CINCH_VERSION: check .env.prod");

$fs = new Filesystem();
$fs->remove(BUILD_DIR);
$fs->mkdir(BUILD_DIR);

$phar = new Phar(BUILD_DIR . "/cinch-$version.phar");
$phar->startBuffering();

$phar->buildFromIterator((new Finder())->in(INCLUDE_DIRS)->files(), __DIR__);
foreach (INCLUDE_FILES as $file)
    $phar->addFile($file);

$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('cinch'));
$phar->stopBuffering();

/* for distribution */
$phargz = $phar->compress(Phar::GZ);

/* local testing, remove extension */
$target = substr($phar->getPath(), 0, -strlen('.phar'));
$fs->rename($phar->getPath(), $target);
$fs->chmod([$target, $phargz->getPath()], 0755);

echo "Created
  * $target
  * {$phargz->getPath()}
";
