<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZF\Maintainer\Phploc;

use RuntimeException;

class Phploc
{
    /**
     * Path to phploc binary
     *
     * @var string
     */
    private $phploc;

    /**
     * Path to grep binary
     *
     * @var string
     */
    private $grep;

    /**
     * Regexp for identifying count within phploc output.
     *
     * Scans only for values in the NCLOC line.
     *
     * @var string
     */
    private $regexp = '/\(NCLOC\)\s+(?P<count>\d+)\s+/';

    public function __construct(string $phploc, string $grep)
    {
        $this->phploc = $phploc;
        $this->grep = $grep;
    }

    /**
     * @throws RuntimeException if phploc + grep has a non-zero exit status
     * @throws RuntimeException if unable to find the NCLOC count in the returned output
     */
    public function count(string $path) : int
    {
        $command = sprintf(
            '%s %s | %s NCLOC',
            $this->phploc,
            $path,
            $this->grep
        );

        exec($command, $output, $status);

        $output = implode(PHP_EOL, $output);
        if ($status !== 0) {
            throw new RuntimeException(sprintf(
                'Unable to calculate phploc for path %s: %s',
                $path,
                implode(PHP_EOL, $output)
            ));
        }

        if (! preg_match($this->regexp, $output, $matches)) {
            throw new RuntimeException(sprintf(
                'Unable to identify NCLOC value for repo %s; received %s',
                $path,
                $output
            ));
        }

        return (int) $matches['count'];
    }
}
