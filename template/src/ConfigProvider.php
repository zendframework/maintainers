<?php
/**
 * @see       https://github.com/{org}/{repo} for the canonical source repository
 * @copyright Copyright (c) {year} Zend Technologies USA Inc. (http://www.zend.com)
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
