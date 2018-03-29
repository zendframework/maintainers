<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright https://github.com/zendframework/maintainers/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer\Lts;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List LTS components.
 */
class Components extends Command
{
    /**
     * @var array
     */
    private $components;

    public function setComponents(array $components)
    {
        $this->components = $components;

        return $this;
    }

    protected function configure()
    {
        $this
            ->setDescription('List LTS components, one per line')
            ->setHelp(
                'List LTS components, one per line. This can be useful when looping in console scripts:'
                . ' for COMPONENT in $(zf-maintainer lts:components | grep "^zend-");do done'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        array_walk($this->components, function ($component) use ($output) {
            $output->writeln($component);
        });

        return 0;
    }
}
