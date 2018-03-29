<?php
/**
 * @see       https://github.com/{org}/{repo} for the canonical source repository
 * @copyright https://github.com/{org}/{repo}/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/{org}/{repo}/blob/master/LICENSE.md New BSD License
 */

namespace {namespace};

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies() : array
    {
        return [
        ];
    }
}
