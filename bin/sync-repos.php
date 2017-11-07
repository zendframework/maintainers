#!/usr/bin/env php
<?php

use Github\Client;

require __DIR__ . '/../vendor/autoload.php';

if (! isset($argv[1])) {
    fwrite(STDERR, printf("Usage: php %s <github token>\n", basename(__FILE__)));
    exit(1);
}

$list = json_decode(
    file_get_contents('https://docs.zendframework.com/zf-mkdoc-theme/scripts/zf-component-list.json'),
    true
);

$client = new Client();
$client->authenticate($argv[1], null, $client::AUTH_URL_TOKEN);

foreach ($list as $comp) {
    $name = basename($comp['url']);
    printf('Updating repository %s... ', $name);
    try {
        $client->repo()->update('zendframework', $name, [
            'name'         => $name,
            'description'  => $comp['description'],
            'homepage'     => $comp['url'],
            'has_wiki'     => false,
        ]);
        echo 'done';
    } catch (\Throwable $e) {
        printf('FAILED: %s', $e->getMessage());
    }

    echo PHP_EOL;
}
