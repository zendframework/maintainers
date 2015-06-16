<?php
namespace ZF\Maintainer;

use RuntimeException;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use ZF\Console\Route;

/**
 * Release components.
 */
class Release
{
    /**
     * List of components to release.
     *
     * @var array
     */
    private $components;

    /**
     * Git executable.
     *
     * @var string
     */
    private $git;

    /**
     * Whether or not verbosity is currently enabled.
     *
     * @var bool
     */
    private $verbose = false;

    /**
     * Lookup map of current version -> next version.
     *
     * @var array
     */
    private $versionIncrementMap = [];

    /**
     * Constructor.
     *
     * Determines the git executable to use if none is provided, raising an
     * exception if none can be found.
     *
     * @param array $components
     * @param null|string $git
     * @throws RuntimeException when unable to discover git executable.
     */
    public function __construct(array $components, $git = null)
    {
        $this->components = $components;

        if (null === $git) {
            $git = shell_exec('which git');
            $git = trim($git);
        }

        if (empty($git)) {
            throw new RuntimeException('Unable to discover git executable');
        }

        // If you use 'hub', git is a function
        if (preg_match('/[ )(\}\{]/', $git)) {
            $git = 'git';
        }

        $this->git = $git;
    }

    /**
     * Release components.
     *
     * Uses the version, exclude, basePath, and verbose (or "v") flags provided
     * with the route to tag the next maintenance release of all components
     * (with the exception of those in the exclude list).
     *
     * Changes directory to the basePath prior to tagging each component.
     *
     * @param Route $route
     * @param Console $console
     * @return int
     */
    public function __invoke(Route $route, Console $console)
    {
        $opts    = $route->getMatches();
        $minor   = $opts['version'];
        $version = $minor;
        $exclude = $opts['exclude'];
        $path    = $opts['basePath'];

        $this->verbose = $opts['verbose'] || $opts['v'];
        $this->emit(sprintf("Using git: %s\n", $this->git), $console, Color::BLUE);

        chdir($path);
        foreach ($this->components as $component) {
            if (in_array($component, $exclude, true)) {
                $this->emit(sprintf("[SKIP] %s\n", $component), $console, Color::GREEN);
                continue;
            }

            $this->emit(sprintf("[START] %s\n", $component), $console, Color::GREEN);

            if (! is_dir($component)) {
                $console->writeLine(sprintf(
                    '[ERROR] Component directory for "%s" does not exist!',
                    $component
                ), Color::WHITE, Color::RED);
                continue;
            }

            chdir($component);
            $version = $this->tagComponent($component, $minor, $version, $console);
            chdir($path);

            $this->emit(sprintf(
                '[DONE] %s tagged at version %s',
                $component,
                $version
            ), $console, Color::GREEN);
        }

        $console->writeLine('');
        $console->writeLine('[DONE] Please verify tags and push.', Color::GREEN);

        return 0;
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
     * @param Console $console
     */
    private function tagComponent($component, $minor, $version, Console $console)
    {
        $currentVersion = $this->detectVersion($version, $minor, $console);
        $newVersion     = $this->incrementVersion($currentVersion);
        $baseCommit     = $this->getBaseCommit($currentVersion);
        $branch         = $this->getBranchName($currentVersion);

        if (0 !== $this->exec(sprintf(
            '%s checkout -b %s %s',
            $this->git,
            $branch,
            $baseCommit
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR][%s] Could not checkout new branch "%s" from base "%s"!',
                $component,
                $branch,
                $baseCommit
            ), Color::WHITE, Color::RED);
            return $currentVersion;
        }

        if ('zend-version' === $component
            && ! $this->updateVersionClass($newVersion, $console)
        ) {
            return $currentVersion;
        }

        if (0 !== $this->exec(sprintf(
            '%s tag -s -m "%s %s" release-%s',
            $this->git,
            $component,
            $newVersion,
            $newVersion
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR][%s] Could not tag new release "%s"!',
                $component,
                $newVersion
            ), Color::WHITE, Color::RED);
            return $currentVersion;
        }

        if (0 !== $this->exec(sprintf(
            '%s checkout master',
            $this->git
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR][%s] Could not checkout master on completion!',
                $component
            ), Color::WHITE, Color::RED);
            return $currentVersion;
        }

        if (0 !== $this->exec(sprintf(
            '%s branch -D %s',
            $this->git,
            $branch
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR][%s] Could not remove branch "%s" on completion!',
                $component,
                $branch
            ), Color::WHITE, Color::RED);
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
     * @param Console $console
     * @return string
     */
    private function detectVersion($version, $minor, Console $console)
    {
        if ($version !== $minor) {
            return $version;
        }

        $command = sprintf(
            '%s tag | grep "release-%s" | sort -V | tail -n 1 | grep -Po "[1-9][0-9]*\.[0-9]+\.[0-9]+"',
            $this->git,
            $minor
        );
        $this->emit('Determining most recent version from tags, using:', $console, Color::BLUE);
        $this->emit('    ' . $command, $console, Color::BLUE);

        $version = shell_exec($command);

        if (empty($version)) {
            $version = sprintf('%s.0', $minor);
        }

        $this->emit('Detected version: ' . $version, $console, Color::BLUE);

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
     * @param Console $console
     * @return int The return value from executing the command.
     */
    private function exec($command, Console $console)
    {
        $this->emit(sprintf("Executing command: %s\n", $command), $console, Color::BLUE);
        $this->emit(exec($command, $output, $return), $console);
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
     * @param Console $console
     * @return bool Whether or not the operations succeeded.
     */
    private function updateVersionClass($version, Console $console)
    {
        $repl     = sprintf("    const VERSION = '%s';", $version);
        $contents = file_get_contents('src/Version.php');
        $contents = preg_replace('/^\s+const VERSION = \'[^\']+\';/m', $repl, $contents);
        file_put_contents('src/Version.php', $contents);

        if (0 !== $this->exec(sprintf(
            '%s commit -a -m "Bump to version %s"',
            $this->git,
            $version
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR][%s] Could not commit updated Version class!',
                $component
            ), Color::WHITE, Color::RED);
            return false;
        }

        return true;
    }

    /**
     * Emit a message.
     *
     * If verbosity is disabled, does nothing.
     *
     * If verbosity is enabled, writes the line using the provided $console,
     * and in the provided $color.
     *
     * @param string $message
     * @param Console $console
     * @param int $color
     */
    private function emit($message, Console $console, $color = Color::WHITE)
    {
        if (! $this->verbose) {
            return;
        }
        $console->writeLine($message, $color);
    }
}
