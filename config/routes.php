<?php // @codingStandardsIgnoreFile
use Zend\Validator\Callback as CallbackValidator;
use ZF\Console\Filter\Explode as ExplodeFilter;

return [
    [
        'name'                 => 'lts-release',
        'route'                => '<version> [--exclude=] [--basePath=] [--verbose|-v]',
        'description'          => 'Tag a new LTS maintenance release of all components. This command will check out a release branch based off the latest maintenance release matching the provided minor release version and tag the new release with no changes. USE THIS ONLY FOR TAGGING COMPONENTS WITH NO CHANGES.',
        'short_description'    => 'Tag a new LTS maintenance release of all components.',
        'options_descriptions' => [
            '<version>'  => 'Minor version against which to create new release.',
            '--exclude'  => 'Comma-separated list of components to exclude from the release; typically those that had changes.',
            '--basePath' => 'Path to component checkouts; if not specified, assumed to be the current working directory.',
            '--verbose'  => 'Verbosity',
        ],
        'defaults' => [
            'basePath' => realpath(getcwd()),
            'exclude'  => [],
        ],
        'filters' => [
            'exclude' => new ExplodeFilter(','),
        ],
        'validators' => [
            'version' => new CallbackValidator(function ($value) {
                return preg_match('/^(0|[1-9][0-9]*)\.[0-9]+$/', $value);
            }),
        ],
    ],
    [
        'name'                 => 'lts-components',
        'route'                => '',
        'description'          => 'List LTS components, one per line. This can be useful when looping in console scripts: for COMPONENT in $(maintainer.php components | grep "^zend-");do done',
        'short_description'    => 'List LTS components, one per line.',
    ],
];
