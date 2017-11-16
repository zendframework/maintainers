<?php // @codingStandardsIgnoreFile
use Zend\Validator\Callback as CallbackValidator;
use Zend\Validator\File\Exists as FileExistsValidator;
use Zend\Validator\Regex as RegexValidator;
use ZF\Console\Filter\Explode as ExplodeFilter;

$fileExists       = new FileExistsValidator();
$minorVersionValidator = new CallbackValidator(function ($value) {
    return preg_match('/^(0|[1-9][0-9]*)\.[0-9]+$/', $value);
});

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
            'version' => $minorVersionValidator,
        ],
    ],
    [
        'name'                 => 'lts-patch',
        'route'                => '--patchfile= --target= --component=',
        'description'          => 'Rewrite a patch made against a component so that it may be applied against the monolithic ZF2 repository.',
        'short_description'    => 'Rewrite a component patch so it may be applied against the ZF2 repo.',
        'options_descriptions' => [
            '--patchfile' => 'Path to the component patchfile to use as the source patch.',
            '--target'    => 'Filename to which to write the rewritten patchfile.',
            '--component' => 'Name of the component (e.g., zend-view, zend-inputfilter, etc) against which the patch was made.',
        ],
        'validators' => [
            'patchfile' => new FileExistsValidator(),
            'target' => new CallbackValidator(function ($value) {
                return is_dir(dirname($value));
            }),
            'component' => new RegexValidator('/^zend-[a-z-]+$/'),
        ],
        'handler' => 'ZF\Maintainer\RewritePatch',
    ],
    [
        'name'                 => 'lts-stage',
        'route'                => '<version> --patchfile= [--verbose|-v]',
        'description'          => 'Checkout a temporary branch based on the last release of the given minor version, and apply the patchfile(s) provided.

If you wish to apply multiple patches, specify them to the --patchfile argument
as a comma-delimited list:

    $ maintainer.php lts-stage 2.4 --patchfile=0001.patch,0002.patch,0003.patch

Patchfiles are applied in the order provided.
',
        'short_description'    => 'Stage a new LTS release by applying the given patchfile(s).',
        'options_descriptions' => [
            '<version>'   => 'Minor version against which to create new release.',
            '--patchfile' => 'Path to the patchfile to apply; specify multiple patchfiles by using a comma-delimiter.',
            '--verbose'   => 'Verbosity',
        ],
        'filters' => [
            'patchfile' => new ExplodeFilter(','),
        ],
        'validators' => [
            'patchfile' => new CallbackValidator(function ($value) use ($fileExists) {
                if (is_string($value)) {
                    return $fileExists->isValid($value);
                }

                foreach ($value as $filename) {
                    if (! $fileExists->isValid($filename)) {
                        return false;
                    }
                }

                return true;
            }),
            'version' => $minorVersionValidator,
        ],
        'handler' => 'ZF\Maintainer\ZfLtsRelease',
    ],
];
