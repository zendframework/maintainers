<?php
namespace ZF\Maintainer;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use ZF\Console\Route;

/**
 * List components.
 */
class Components
{
    /**
     * @var array
     */
    private $components;

    /**
     * Constructor
     *
     * @param array $components
     */
    public function __construct(array $components)
    {
        $this->components = $components;
    }

    /**
     * List the components
     *
     * @param Route $route
     * @param Console $console
     * @return int
     */
    public function __invoke(Route $route, Console $console)
    {
        array_walk($this->components, function ($component) use ($console) {
            $console->writeLine($component);
        });

        return 0;
    }
}
