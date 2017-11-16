<?php // @codingStandardsIgnoreFile
use Zend\Validator\Callback as CallbackValidator;
use Zend\Validator\File\Exists as FileExistsValidator;
use ZF\Console\Filter\Explode as ExplodeFilter;

$fileExists       = new FileExistsValidator();
$minorVersionValidator = new CallbackValidator(function ($value) {
    return preg_match('/^(0|[1-9][0-9]*)\.[0-9]+$/', $value);
});

return [
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
