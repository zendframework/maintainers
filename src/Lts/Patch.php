<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer\Lts;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rewrite a patchfile from a component for use in the ZF2 repo.
 */
class Patch extends Command
{
    private $map = [
        'eventmanager'     => 'EventManager',
        'inputfilter'      => 'InputFilter',
        'modulemanager'    => 'ModuleManager',
        'permissions-acl'  => 'Permissions/Acl',
        'permissions-rbac' => 'Permissions/Rbac',
        'progressbar'      => 'ProgressBar',
        'servicemanager'   => 'ServiceManager',
    ];

    protected function configure()
    {
        $this
            ->setName('lts:patch')
            ->setDescription('Rewrite a component patch so it may be applied against the ZF2 repo')
            ->setHelp(
                'Rewrite a patch made against a component so that it may be applied'
                . ' against the monolithic ZF2 repository.'
            )
            ->addOption(
                'patchfile',
                'p',
                InputOption::VALUE_REQUIRED,
                'Path to the component patchfile to use as the source patch'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'Filename to which to write the rewritten patchfile'
            )
            ->addOption(
                'component',
                'c',
                InputOption::VALUE_REQUIRED,
                'Name of the component (e.g., zend-view, zend-inputfilter, etc) against which the patch was made'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $patchfile = $input->getOption('patchfile');
        if (! $patchfile || ! file_exists($patchfile)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid patchfile; file "%s" does not exist',
                $patchfile
            ));
        }

        $target = $input->getOption('target');
        if (! $target || ! is_dir(dirname($target))) {
            throw new InvalidArgumentException(sprintf(
                'Invalid target; directory "%s" does not exist',
                dirname($target)
            ));
        }

        $component = $input->getOption('component');
        if (! preg_match('/^zend-[a-z-]+$/', $component)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid component name: "%s"',
                $component
            ));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $patchfile = $input->getOption('patchfile');
        $target    = $input->getOption('target');
        $component = $input->getOption('component');

        if ($component === 'zend-i18n-resources') {
            $this->rewriteResources($patchfile, $target);
            return;
        }

        $this->rewrite($component, $patchfile, $target);
    }

    private function rewrite($component, $patchfile, $target)
    {
        $libraryPath = $this->resolveLibraryPath($component);
        $testsPath   = $this->resolveTestsPath($component);

        $patch = file_get_contents($patchfile);

        $patch = preg_replace('#^ src/#m', ' ' . $libraryPath, $patch);
        $patch = preg_replace('#^ test/#m', ' ' . $testsPath, $patch);
        $patch = preg_replace(
            '#^(diff --git a/)src/([^ ]+)( b/)src/(.*)$#m',
            '$1' . $libraryPath . '$2$3' . $libraryPath . '$4',
            $patch
        );
        $patch = preg_replace(
            '#^((?:---|\+\+\+) (?:a|b)/)src/(.*)$#m',
            '$1' . $libraryPath . '$2',
            $patch
        );
        $patch = preg_replace(
            '#^(diff --git a/)test/([^ ]+)( b/)test/(.*)$#m',
            '$1' . $testsPath . '$2$3' . $testsPath . '$4',
            $patch
        );
        $patch = preg_replace(
            '#^((?:---|\+\+\+) (?:a|b)/)test/(.*)$#m',
            '$1' . $testsPath . '$2',
            $patch
        );

        file_put_contents($target, $patch);
    }

    private function rewriteResources($patchfile, $target)
    {
        $patch = file_get_contents($patchfile);

        $patch = preg_replace('#^ (languages/)#m', ' resources/$1', $patch);
        $patch = preg_replace(
            '#^(diff --git a/)(languages/.*?b/)(languages/.*)$#m',
            '$1resources/$2resources/$3',
            $patch
        );
        $patch = preg_replace(
            '#^((?:---|\+\+\+) (?:a|b)/)(languages/.*)$#m',
            '$1resources/$2',
            $patch
        );

        file_put_contents($target, $patch);
    }

    private function resolveLibraryPath($component)
    {
        return sprintf('library/Zend/%s/', $this->normalizeComponent($component));
    }

    private function resolveTestsPath($component)
    {
        return sprintf('tests/ZendTest/%s/', $this->normalizeComponent($component));
    }

    private function normalizeComponent($component)
    {
        $normalized = str_replace('zend-', '', $component);
        if (isset($this->map[$normalized])) {
            return $this->map[$normalized];
        }

        return ucfirst($normalized);
    }
}
