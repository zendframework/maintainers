<?php
namespace ZF\Maintainer;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use ZF\Console\Route;

/**
 * Rewrite a patchfile from a component for use in the ZF2 repo.
 */
class RewritePatch
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

    /**
     * Rewrite the patchfile.
     *
     * - --patchfile - original patch
     * - --target - filename to save new patch to
     * - --component - component for which the patch was written
     *
     * @param Route $route
     * @param Console $console
     * @return int
     */
    public function __invoke(Route $route, Console $console)
    {
        $opts = $route->getMatches();
        $patchfile = $opts['patchfile'];
        $target    = $opts['target'];
        $component = $opts['component'];

        if ($component === 'zend-i18n-resources') {
            return $this->rewriteResources($patchfile, $target, $console);
        }

        return $this->rewrite($component, $patchfile, $target, $console);
    }

    private function rewrite($component, $patchfile, $target, Console $console)
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
        return 0;
    }

    private function rewriteResources($patchfile, $target, Console $console)
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
        return 0;
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
