#!/usr/bin/env php
<?php
use Zend\Console\Console;
use ZF\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

$version = '0.0.1';

$application = new Application(
    'ZF Maintainer',
    $version,
    include __DIR__ . '/../config/routes.php',
    Console::getInstance()
);

$exit = $application->run();
exit($exit);
