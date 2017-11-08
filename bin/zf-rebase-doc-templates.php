#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

if (! file_exists('composer.json')) {
    fwrite(
        STDERR,
        'File composer.json does not exist, please call script from main repository directory' . PHP_EOL
    );
    exit(1);
}

$composer = json_decode(file_get_contents('composer.json'), true);
if (! isset($composer['name']) || ! preg_match('/^([a-z-]+)\/([a-z-]+)$/', $composer['name'], $m)) {
    fwrite(
        STDERR,
        'Cannot extract repository name from composer.json, please check value of "name" key in that file.'
    );
    exit(1);
}
$org = $m[1];
$repo = $m[2];

// if there is no docs directory create one
if (! is_dir('docs')) {
    // if there is doc directory rename it to docs
    if (is_dir('doc')) {
        system('git mv doc docs');
    } else {
        mkdir('docs', 0775);
    }
}

$docs = [
    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    'ISSUE_TEMPLATE.md',
    'PULL_REQUEST_TEMPLATE.md',
    'SUPPORT.md',
];

$replace = [
    '{org}' => $org,
    '{repo}' => $repo,
    '{category}' => strpos($repo, 'zf-') === 0
        ? 'apigility'
        : (strpos($repo, 'zend-expressive') === 0
            ? 'expressive'
            : 'components'),
];

if (file_exists('CONTRIBUTING.md')) {
    system('git mv CONTRIBUTING.md docs/CONTRIBUTING.md');
}

if (file_exists('CONDUCT.md')) {
    system('git mv CONDUCT.md docs/CODE_OF_CONDUCT.md');
}

foreach ($docs as $file) {
    if (file_exists('docs/' . $file)) {
        unlink('docs/' . $file);
    }

    $content = file_get_contents(__DIR__ . '/../template/docs/' . $file);
    $content = strtr($content, $replace);

    file_put_contents('docs/' . $file, $content);
}

// Update LICENSE.md - template + use current year
$year = $startYear = date('Y');
if (file_exists('LICENSE.md')) {
    $content = file_get_contents('LICENSE.md');
    if (preg_match('/Copyright \(c\) (\d{4})/', $content, $m)) {
        $startYear = $m[1];
    } else {
        fwrite(
            STDERR,
            'Cannot match year or year range in current LICENSE.md file; using current year only.' . PHP_EOL
        );
    }
}

$yearReplacement = $startYear < $year ? sprintf('%s-%s', $startYear, $year) : $year;
$content = file_get_contents(__DIR__ . '/../template/LICENSE.md');
$content = str_replace('{year}', $yearReplacement, $content);
file_put_contents('LICENSE.md', $content);

// .coveralls.yml
file_put_contents('.coveralls.yml', file_get_contents(__DIR__ . '/../template/.coveralls.yml'));

// check if repository has documentation
$hasDocs = file_exists('mkdocs.yml');

// .gitattributes
$content = preg_split("/\r?\n|\r/", trim(file_get_contents(__DIR__ . '/../template/.gitattributes')));
if (! $hasDocs) {
    $content = array_diff($content, ['/mkdocs.yml export-ignore']);
}
if (file_exists('phpbench.json')) {
    // the directory name with benchmarks is not consistent across repositories, we check then both
    if (is_dir('benchmark')) {
        $content[] = '/benchmark export-ignore';
    }
    if (is_dir('benchmarks')) {
        $content[] = '/benchmarks export-ignore';
    }
    $content[] = '/phpbench.json export-ignore';
}
if (file_exists('.docheader')) {
    if (isset($composer['require-dev']['malukenho/docheader'])) {
        $content[] = '/.docheader export-ignore';
    } else {
        unlink('.docheader');
    }
}
natsort($content);
file_put_contents('.gitattributes', implode("\n", $content) . "\n");

// .gitignore
$content = preg_split("/\r?\n|\r/", trim(file_get_contents(__DIR__ . '/../template/.gitignore')));
if (! $hasDocs) {
    $content = array_diff($content, [
       'docs/html/',
       'zf-mkdoc-theme/',
       'zf-mkdoc-theme.tgz',
    ]);
}
file_put_contents('.gitignore', implode("\n", $content) . "\n");

// .travis.yml - create only when does not exist
if (! file_exists('.travis.yml')) {
    copy(__DIR__ . '/../template/.travis.yml', '.travis.yml');
}

// composer.json
// - checks repository description
// - checks order of sections
// - removes default type library
// - updates scripts
// - updates license
// - updates support links
// - updates "config"
// - removes "minimum-stability" and "prefer-stable"
// - checks keywords
// @todo: check branch-alias

$templateContent = json_decode(file_get_contents(__DIR__ . '/../template/composer.json'), true);

$sectionOrder = [
    'name',
    'description',
    'type',
    'license',
    'keywords',
    'support',
    'require',
    'require-dev',
    'provide',
    'conflict',
    'suggest',
    'autoload',
    'autoload-dev',
    'config',
    'extra',
    'bin',
    'scripts',
];

$content = $composer;
if (isset($content['type']) && $content['type'] === 'library') {
    unset($content['type']);
}
$content['license'] = $templateContent['license'];
$content['support'] = $templateContent['support'];
if (! $hasDocs) {
    unset($content['support']['docs']);
}
foreach ($content['support'] as &$supportLink) {
    $supportLink = strtr($supportLink, $replace);
}

$content['config'] = $templateContent['config'];
$content['scripts'] = $templateContent['scripts'];

// add license-check script only when we use .docheader library
if (file_exists('.docheader')) {
    array_unshift($content['scripts']['check'], '@license-check');
    $content['scripts']['license-check'] = 'docheader check src/ test/';
}

// check keywords - always we must have "zf" and "zendframework" keywords
if (empty($content['keywords'])) {
    fwrite(STDERR, 'Missing "keywords" in composer.json' . PHP_EOL);
} else {
    $hasZf = false;
    $hasZendframework = false;
    foreach ($content['keywords'] as &$keyword) {
        if ($keyword === 'zf2') {
            $keyword = 'zf';
            $hasZf = true;
        } elseif ($keyword === 'zf') {
            $hasZf = 'zf';
        } elseif ($keyword === 'zendframework') {
            $hasZendframework = true;
        }
    }

    if (! $hasZendframework) {
        array_unshift($content['keywords'], 'zendframework');
    }
    if (! $hasZf) {
        array_unshift($content['keywords'], 'zf');
    }
}

unset($content['minimum-stability'], $content['prefer-stable']);

$list = json_decode(
    file_get_contents('https://docs.zendframework.com/zf-mkdoc-theme/scripts/zf-component-list.json'),
    true
);

$description = null;
foreach ($list as $component) {
    if (strpos($component['url'], '/' . $repo . '/') !== false) {
        $description = rtrim($component['description'], '.');
        break;
    }
}

if ($description !== null) {
    $content['description'] = $description;
}

// sort section in composer:

uksort($content, function ($a, $b) use ($sectionOrder) {
    $ia = array_search($a, $sectionOrder);
    $ib = array_search($b, $sectionOrder);

    if ($ia === $ib) {
        return 0;
    }

    if ($ia === false) {
        return 1;
    }

    if ($ib === false) {
        return -1;
    }

    if ($ia < $ib) {
        return -1;
    }

    return 1;
});

file_put_contents(
    'composer.json',
    json_encode(
            $content,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL
);

// update mkdocs.yml
if ($hasDocs) {
    $content = file_get_contents('mkdocs.yml');
    $content = preg_replace('/docs_dir:.*/', 'docs_dir: docs/book', $content);
    $content = preg_replace('/site_dir:.*/', 'site_dir: docs/html', $content);
    $content = preg_replace(
        '/Copyright \(c\) \d{4}(-\d{4})? /',
        'Copyright (c) ' . $yearReplacement . ' ',
        $content
    );
    file_put_contents('mkdocs.yml', $content);
}

// README.md

$templateContent = file_get_contents(__DIR__ . '/../template/README.md');
$buildBadge = null;
if (preg_match('/\[\!\[Build Status\].*/', $templateContent, $m)) {
    $buildBadge = strtr($m[0], $replace);
}
$coverageBadge = null;
if (preg_match('/\[\!\[Coverage Status\].*/', $templateContent, $m)) {
    $coverageBadge = strtr($m[0], $replace);
}

$content = file_get_contents('README.md');
if ($buildBadge) {
    $content = preg_replace('/\[\!\[Build Status\].*/', $buildBadge, $content);
}
if ($coverageBadge) {
    $content = preg_replace('/\[\!\[Coverage Status\].*/', $coverageBadge, $content);
}

// replace link to the docs
$content = str_replace('zendframework.github.io', 'docs.zendframework.com', $content);
file_put_contents('README.md', $content);
