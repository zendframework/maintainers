<?php
/**
 * @see       https://github.com/{org}/{repo} for the canonical source repository
 * @copyright Copyright (c) {year} Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/{org}/{repo}/blob/master/LICENSE.md New BSD License
 */

namespace {namespace-test};

use PHPUnit\Framework\TestCase;
use {namespace}\ConfigProvider;

class ConfigProviderTest extends TestCase
{
    public function setUp()
    {
        $this->provider = new ConfigProvider();
    }

    public function testInvocationReturnsArray()
    {
        $config = ($this->provider)();
        $this->assertInternalType('array', $config);
        return $config;
    }

    /**
     * @depends testInvocationReturnsArray
     */
    public function testReturnedArrayContainsDependencies(array $config)
    {
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertInternalType('array', $config['dependencies']);
    }
}
