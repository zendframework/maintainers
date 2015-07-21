<?php
namespace ZF\Maintainer;

use RuntimeException;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\ColorInterface as Color;
use ZF\Console\Route;

/**
 * Create a new LTS release based on the previous LTS release and provided patchfiles.
 */
class ZfLtsRelease
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
     * Tag a new ZF2 LTS release.
     *
     * @param Route $route
     * @param Console $console
     * @return int
     */
    public function __invoke(Route $route, Console $console)
    {
        $opts       = $route->getMatches();
        $minor      = $opts['version'];
        $patchfiles = $opts['patchfile'];

        $this->verbose = $opts['verbose'] || $opts['v'];

        $currentVersion = $this->detectVersion($minor, $console);

        // checkout release-$minor branch based on release-$currentVersion
        if (0 !== $this->exec(sprintf(
            '%s checkout -b release-%s release-%s',
            $this->git,
            $minor,
            $currentVersion
        ), $console)) {
            $console->writeLine(sprintf(
                '[ERROR] Could not create new branch release-%s based on tag release-%s!',
                $minor,
                $currentVersion
            ), Color::WHITE, Color::RED);
            return 1;
        }

        // apply patchfile
        foreach ($patchfiles as $patchfile) {
            if (0 !== $this->exec(sprintf(
                '%s am < %s',
                $this->git,
                $patchfile
            ), $console)) {
                $console->writeLine(sprintf(
                    '[ERROR] Could not cleanly apply patchfile "%s"!',
                    $patchfile
                ), Color::WHITE, Color::RED);
                return 1;
            }
        }

        // Create message for release
        $message = $this->getCommitMessages($currentVersion);
        if (false === $message) {
            $console->writeLine(
                '[ERROR] Could not retrieve patch messages!',
                Color::WHITE,
                Color::RED
            );
            return 1;
        }

        $nextVersion = $this->incrementVersion($currentVersion);

        // Update VERSION constant
        $this->updateVersionConstant($nextVersion);

        // Update README.md file
        $this->updateReadme($nextVersion);

        // Update CHANGELOG.md file
        $this->updateChangelog($nextVersion, $message);

        // Commit version information
        $this->commitVersionBump($nextVersion, $console);

        $message = sprintf(
            "Zend Framework %s\n\n%s",
            $nextVersion,
            $message
        );

        $console->writeLine(
            '[DONE] Please verify the patch, and then execute:',
            Color::GREEN
        );
        $console->writeLine(sprintf(
            '    git tag -s -m "%s" release-%s',
            $message,
            $nextVersion
        ));
    }

    /**
     * Detect the latest maintenance release for the current minor version.
     *
     * The latest maintenance release is determined by sorting
     * the available tags matching the minor version.
     *
     * @param string $minor
     * @param Console $console
     * @return string
     */
    private function detectVersion($minor, Console $console)
    {
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

        $version = trim($version);

        $this->emit('Detected version: ' . $version, $console, Color::BLUE);

        return $version;
    }

    /**
     * Retrieve a formatted list of commit messages to use for the tag message.
     *
     * @param string $startVersion
     * @return false|string
     */
    private function getCommitMessages($startVersion)
    {
        exec(sprintf(
            '%s log --oneline release-%s..HEAD',
            $this->git,
            $startVersion
        ), $output, $return);

        if (0 !== $return) {
            return false;
        }

        return array_reduce($output, function ($carry, $item) {
            $message = preg_replace('/^[a-f0-9]+ /m', '- ', trim($item));
            return ($carry === '') ? $message : $carry . "\n" . $message;
        }, '');
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
        if (substr($version, -2) === '.0') {
            return $version;
        }

        preg_match('/^(?P<minor>\d+\.\d+)\.(?P<patch>\d+)$/', $version, $matches);
        $patch = (int) $matches['patch'] + 1;
        return sprintf('%s.%d', $matches['minor'], $patch);
    }

    /**
     * Update the VERSION constant in the Zend\Version\Version classfile.
     *
     * @param string $version New version to use.
     */
    private function updateVersionConstant($version)
    {
        $versionClassFile = 'library/Zend/Version/Version.php';
        $contents = file_get_contents($versionClassFile);
        $repl     = sprintf("    const VERSION = '%s';", $version);
        $contents = preg_replace('/^\s+const VERSION = \'[^\']+\';/m', $repl, $contents);
        file_put_contents($versionClassFile, $contents);
    }

    /**
     * Update the README file with the new version information.
     *
     * @param string $version
     */
    private function updateReadme($version)
    {
        preg_match('/^(?P<minor>\d+\.\d+)/', $version, $matches);
        $minor    = $matches['minor'];
        $date     = date('d F Y');
        $template = __DIR__ . '/../template/ZF2-README.md';

        $contents = file_get_contents($template);
        $contents = str_replace(
            ['{MINOR}', '{VERSION}', '{DATE}'],
            [$minor,    $version,    $date],
            $contents
        );
        file_put_contents('README.md', $contents);
    }

    /**
     * Update the CHANGELOG with the new version information.
     *
     * @param string $version
     * @param string $changelog
     */
    private function updateChangelog($version, $changelog)
    {
        $changelogFile = 'CHANGELOG.md';
        $date          = date('Y-m-d');

        $contents = file_get_contents($changelogFile);
        $contents = str_replace(
            '# CHANGELOG',
            sprintf("# CHANGELOG\n\n## %s (%s)\n\n%s", $version, $date, $changelog),
            $contents
        );
        file_put_contents($changelogFile, $contents);
    }

    /**
     * Commit the changes made to the VERSION constant, README, and CHANGELOG.
     *
     * @param string $version
     * @param Console $console
     */
    private function commitVersionBump($version, Console $console)
    {
        if (0 !== $this->exec(sprintf(
            '%s commit -a -m "Prepare for %s"',
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
