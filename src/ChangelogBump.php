<?php
namespace ZF\Maintainer;

use RuntimeException;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use ZF\Console\Route;

/**
 * Create a new branch and create a new changelog version entry.
 */
class ChangelogBump
{
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
     * Constructor.
     *
     * Determines the git executable to use if none is provided, raising an
     * exception if none can be found.
     *
     * @param null|string $git
     * @throws RuntimeException when unable to discover git executable.
     */
    public function __construct($git = null)
    {
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
     * Create a new local branch and bump the changelog version entry.
     *
     * @param Route $route
     * @param Console $console
     * @return int
     */
    public function __invoke(Route $route, Console $console)
    {
        $opts       = $route->getMatches();
        $version    = $opts['version'];
        $base       = $opts['base'];

        $this->verbose = $opts['verbose'] || $opts['v'];

        // checkout version/bump branch based on $base
        if (0 !== $this->exec(sprintf(
            '%s checkout -b version/bump %s',
            $this->git,
            $base
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR] Could not create new version/bump branch based on branch %s!',
                $base
            ), Color::WHITE, Color::RED);
            return 1;
        }

        // Update CHANGELOG.md file
        $this->updateChangelog($version);

        // Commit version bump
        $this->commitVersionBump($version, $console);

        $message = sprintf('[DONE] Please verify and merge the branch back to %s', $base);
        if ($base === 'master') {
            $message .= ' as well as develop';
        }

        $console->writeLine($message, Color::GREEN);

        $console->writeLine('Once done merging, remove this branch using:');
        $console->writeLine('    git branch -d version/bump');
        return 0;
    }

    /**
     * Update the CHANGELOG with the new version information.
     *
     * @param string $version
     */
    private function updateChangelog($version)
    {
        $changelogFile = 'CHANGELOG.md';

        $changelog = sprintf("\n\n## %s - TBD\n\n", $version)
            . "### Added\n\n- Nothing.\n\n"
            . "### Changed\n\n- Nothing.\n\n"
            . "### Deprecated\n\n- Nothing.\n\n"
            . "### Removed\n\n- Nothing.\n\n"
            . "### Fixed\n\n- Nothing.\n\n";

        $contents = file_get_contents($changelogFile);
        $contents = preg_replace(
            "/^(\# Changelog\n\n.*?)(\n\n\#\# )/s",
            '$1' .  $changelog . '## ',
            $contents
        );
        file_put_contents($changelogFile, $contents);
    }

    /**
     * Commit the changes made to the CHANGELOG.
     *
     * @param string $version
     * @param Console $console
     */
    private function commitVersionBump($version, Console $console)
    {
        if (0 !== $this->exec(sprintf(
            '%s commit -a -m "Bumped to next dev version (%s)"',
            $this->git,
            $version
        ), $console)) {
            $console->writeLine('[ERROR] Could not commit version bump changes!', Color::WHITE, Color::RED);
        }
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
