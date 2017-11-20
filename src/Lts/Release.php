<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer\Lts;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Release components.
 */
class Release extends Command
{
    /**
     * List of components to release.
     *
     * @var array
     */
    private $components;

    /**
     * Lookup map of current version -> next version.
     *
     * @var array
     */
    private $versionIncrementMap = [];

    public function setComponents(array $components)
    {
        $this->components = $components;

        return $this;
    }

    protected function configure()
    {
        $this
            ->setDescription('Tag a new LTS maintenance release of all components')
            ->setHelp(
                'Tag a new LTS maintenance release of all components.'
                . ' This command will check out a release branch based off the latest'
                . ' maintenance release matching the provided minor release version'
                . ' and tag the new release with no changes.'
                . PHP_EOL . PHP_EOL
                . '<info>USE THIS ONLY FOR TAGGING COMPONENTS WITH NO CHANGES.</info>'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'Minor version against which to create new release'
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Component to exclude from the release; typically those that had changes;'
                   . ' allowed to use multiple times',
                []
            )
            ->addOption(
                'basePath',
                'b',
                InputOption::VALUE_REQUIRED,
                'Path to component checkouts; if not specified, assumed to be the current working directory',
                realpath(getcwd())
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $version = $input->getArgument('version');
        if (! preg_match('/^(0|[1-9]\d*)\.\d+$/', $version)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid version provided: "%s"',
                $version
            ));
        }

        $path = $input->getOption('basePath');
        if (! is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid base path provided; directory "%s" does not exist',
                $path
            ));
        }
    }

    /**
     * Release components.
     *
     * Uses the version, exclude, basePath, and verbose (or "v") flags provided
     * with the route to tag the next maintenance release of all components
     * (with the exception of those in the exclude list).
     *
     * Changes directory to the basePath prior to tagging each component.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $minor    = $input->getArgument('version');
        $version  = $minor;
        $excludes = $input->getOption('exclude');
        $path     = $input->getOption('basePath');

        chdir($path);
        foreach ($this->components as $component) {
            if (in_array($component, $excludes, true)) {
                $output->writeln(
                    sprintf('<info>[SKIP] %s</info>', $component),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                continue;
            }

            $output->writeln(
                sprintf('<info>[START] %s</info>', $component),
                OutputInterface::VERBOSITY_VERBOSE
            );

            if (! is_dir($component)) {
                $output->writeln(sprintf(
                    '<error>[ERROR] Component directory for "%s" does not exist!</error>',
                    $component
                ));
                continue;
            }

            chdir($component);
            $version = $this->tagComponent($component, $minor, $version, $output);
            chdir($path);

            $output->writeln(
                sprintf('<info>[DONE] %s tagged at version %s</info>', $component, $version),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }

        $output->writeln('');
        $output->writeln('<info>[DONE] Please verify tags and push the following tag:</info>');
        $output->writeln('       release-' . $version);
    }

    /**
     * Tag a single component.
     *
     * Given the minor version and latest current version (which it will detect
     * if it is identical to the minor version), checks out a temporary branch
     * based on the current version and tags it with the next maintenance
     * version.
     *
     * If the minor version did not exist previously, tags a .0 version.
     *
     * Once complete, removes the temporary branch.
     *
     * @param string $component
     * @param string $minor Minor version
     * @param string $version Latest release on current minor branch
     * @param OutputInterface $output
     * @return string
     */
    private function tagComponent($component, $minor, $version, OutputInterface $output)
    {
        $currentVersion = $this->detectVersion($version, $minor, $output);
        $newVersion     = $this->incrementVersion($currentVersion);
        $baseCommit     = $this->getBaseCommit($currentVersion);
        $branch         = $this->getBranchName($currentVersion);

        if (0 !== $this->exec(sprintf('git checkout -b %s %s', $branch, $baseCommit), $output)) {
            $output->writeln(sprintf(
                '<error>[ERROR][%s] Could not checkout new branch "%s" from base "%s"!</error>',
                $component,
                $branch,
                $baseCommit
            ));

            return $currentVersion;
        }

        if ('zend-version' === $component
            && ! $this->updateVersionClass($newVersion, $output)
        ) {
            return $currentVersion;
        }

        if (0 !== $this->exec(sprintf(
            'git tag -s -m "%s %s" release-%s',
            $component,
            $newVersion,
            $newVersion
        ), $output)) {
            $output->writeln(sprintf(
                '<error>[ERROR][%s] Could not tag new release "%s"!</error>',
                $component,
                $newVersion
            ));

            return $currentVersion;
        }

        if (0 !== $this->exec('git checkout master', $output)) {
            $output->writeln(sprintf(
                '<error>[ERROR][%s] Could not checkout master on completion!</error>',
                $component
            ));

            return $currentVersion;
        }

        if (0 !== $this->exec(sprintf('git branch -D %s', $branch), $output)) {
            $output->writeln(sprintf(
                '[ERROR][%s] Could not remove branch "%s" on completion!',
                $component,
                $branch
            ));

            return $currentVersion;
        }

        return $currentVersion;
    }

    /**
     * Detect the latest maintenance release for the current minor version.
     *
     * If $version is not the same as $minor; that value is returned
     * immediately.
     *
     * Otherwise, the latest maintenance release is determined by sorting
     * the available tags matching the minor version.
     *
     * @param string $version
     * @param string $minor
     * @param OutputInterface $output
     * @return string
     */
    private function detectVersion($version, $minor, OutputInterface $output)
    {
        if ($version !== $minor) {
            return $version;
        }

        $command = sprintf(
            'git tag | grep "release-%s" | sort -V | tail -n 1 | grep -Po "[1-9][0-9]*\.[0-9]+\.[0-9]+"',
            $minor
        );
        $output->writeln(
            '<comment>Determining most recent version from tags, using:</comment>',
            OutputInterface::VERBOSITY_VERBOSE
        );
        $output->writeln(
            '    ' . $command,
            OutputInterface::VERBOSITY_VERBOSE
        );

        $version = trim(shell_exec($command));

        if (empty($version)) {
            $version = sprintf('%s.0', $minor);
        }

        $output->writeln(
            sprintf('<comment>Detected version: %s</comment>', $version),
            OutputInterface::VERBOSITY_VERBOSE
        );

        return $version;
    }

    /**
     * Get the base commit or branch to tag from.
     *
     * If the version is a .0 version, uses "develop" as the base branch.
     *
     * Otherwise, uses the provided version.
     *
     * @param string $version
     * @return string
     */
    private function getBaseCommit($version)
    {
        if (substr($version, -2) === '.0') {
            return 'develop';
        }

        return sprintf('release-%s', $version);
    }

    /**
     * Determine the name of the temporary branch to create.
     *
     * Uses the minor version for the given version.
     *
     * @param string $version
     * @return string
     * @throws RuntimeException if the $version is malformed.
     */
    private function getBranchName($version)
    {
        if (! preg_match('/^(?P<minor>\d+\.\d+)\.\d+$/', $version, $matches)) {
            throw new RuntimeException(sprintf(
                'Invalid version detected: %s; cannot proceed',
                $version
            ));
        }

        return sprintf('release-%s', $matches['minor']);
    }

    /**
     * Determine the next patch version.
     *
     * For .0 versions, the provided $version is returned verbatim; otherwise,
     * increments the patch version.
     *
     * @param string $version
     * @return string
     */
    private function incrementVersion($version)
    {
        if (isset($this->versionIncrementMap[$version])) {
            return $this->versionIncrementMap[$version];
        }

        if (substr($version, -2) === '.0') {
            $this->versionIncrementMap[$version] = $version;
            return $version;
        }

        preg_match('/^(?P<minor>\d+\.\d+)\.(?P<patch>\d+)$/', $version, $matches);
        $patch = (int) $matches['patch'] + 1;
        $this->versionIncrementMap[$version] = sprintf('%s.%d', $matches['minor'], $patch);
        return $this->versionIncrementMap[$version];
    }

    /**
     * Execute a command.
     *
     * If verbosity is enabled, emits the console command, and the output from
     * executing it.
     *
     * @param string $command
     * @param OutputInterface $output
     * @return int The return value from executing the command.
     */
    private function exec($command, OutputInterface $output)
    {
        $output->writeln(
            sprintf('Executing command: <comment>%s</comment>', $command),
            OutputInterface::VERBOSITY_VERBOSE
        );
        $output->writeln(
            exec($command, $out, $return),
            OutputInterface::VERBOSITY_VERBOSE
        );

        return $return;
    }

    /**
     * Update Zend\Version\Version::VERSION.
     *
     * This method is executed when tagging the zend-version component; it
     * pulls the contents of the `Zend\Version\Version` class, and replaces the
     * value of the `VERSION` constant with the provided $version.
     *
     * On completion, it commits the file and returns.
     *
     * @param string $version
     * @param OutputInterface $output
     * @return bool Whether or not the operations succeeded.
     */
    private function updateVersionClass($version, OutputInterface $output)
    {
        $repl     = sprintf("    const VERSION = '%s';", $version);
        $contents = file_get_contents('src/Version.php');
        $contents = preg_replace('/^\s+const VERSION = \'[^\']+\';/m', $repl, $contents);
        file_put_contents('src/Version.php', $contents);

        if (0 !== $this->exec(sprintf('git commit -a -m "Bump to version %s"', $version), $output)) {
            $output->writeln('<error>[ERROR][zend-version] Could not commit updated Version class!</error>');

            return false;
        }

        return true;
    }
}
