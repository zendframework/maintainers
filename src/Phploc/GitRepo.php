<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZF\Maintainer\Phploc;

use RuntimeException;

use function glob;
use function is_dir;
use function rmdir;
use function unlink;

use const GLOB_BRACE;
use const GLOB_MARK;

class GitRepo
{
    /**
     * Name or path to git binary.
     *
     * @var string
     */
    private $git;

    /**
     * Path to working directory under which to clone.
     *
     * @var string
     */
    private $workdir;

    public function __construct(string $git, string $workdir)
    {
        $this->git = $git;
        $this->workdir = $workdir;
    }

    /**
     * @return string The path to the created repository
     * @throws RuntimeException if unable to clone the repository
     */
    public function clone(string $repository) : string
    {
        [$org, $repo] = explode('/', $repository, 2);
        $path = sprintf('%s/%s', $this->workdir, $repo);
        $command = sprintf(
            '%s clone git://github.com/%s.git %s',
            $this->git,
            $repository,
            $path
        );
        exec($command, $output, $status);

        if (0 !== $status) {
            throw new RuntimeException(sprintf(
                'Unable to clone %s to %s: %s',
                $repository,
                $path,
                implode(PHP_EOL, $output)
            ));
        }

        return $path;
    }

    public function cleanup(string $path) : void
    {
        foreach (glob($path . '/{,.}[!.,!..]*', GLOB_MARK|GLOB_BRACE) as $file) {
            is_dir($file) ? $this->cleanup($file) : unlink($file);
        }
        rmdir($path);
    }
}
