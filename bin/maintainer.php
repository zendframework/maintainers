#!/usr/bin/env php
<?php
use Zend\Console\Console;
use ZF\Console\Application;
use ZF\Console\Dispatcher;
use ZF\Maintainer\Components;
use ZF\Maintainer\Release;

require __DIR__ . '/../vendor/autoload.php';

$version = '0.0.1';

$dispatcher  = new Dispatcher();
$dispatcher->map('lts-release', new Release(include __DIR__ . '/../config/components.php'));
$dispatcher->map('lts-components', new Components(include __DIR__ . '/../config/components.php'));

$application = new Application(
    'ZF Maintainer',
    $version,
    include __DIR__ . '/../config/routes.php',
    Console::getInstance(),
    $dispatcher
);

$exit = $application->run();
exit($exit);
