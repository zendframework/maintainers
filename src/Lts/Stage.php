<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer\Lts;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create a new LTS release based on the previous LTS release and provided patchfiles.
 */
class Stage extends Command
{
    protected function configure()
    {
        $this
            ->setName('lts:stage')
            ->setDescription('Stage a new LTS release by applying the given patchfile(s)')
            ->setHelp(
                'Checkout a temporary branch based on the last release of the given minor version,'
                . ' and apply the patchfile(s) provided.'
                . PHP_EOL . PHP_EOL
                . 'If you wish to apply multiple patches, specify them to the --patchfile argument:'
                . PHP_EOL . PHP_EOL
                . '  $ zf-maintainer lts:stage 2.4 --patchfile=0001.patch --patchfile=0002.patch --patchfile=0003.patch'
                . PHP_EOL . PHP_EOL
                . 'Patchfiles are applied in the order provided.'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'Minor version against which to create new release'
            )
            ->addOption(
                'patchfile',
                'p',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Path to the patchfile to apply; allowed to use multiple times'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $version = $input->getArgument('version');
        if (! preg_match('/^(0|[1-9]\d*)\.\d+$/', $version)) {
            throw new InvalidArgumentException('Invalid version provided');
        }

        $patchfiles = $input->getOption('patchfile');
        if (! $patchfiles) {
            throw new InvalidArgumentException('Missing patchfile option; required at least one patchfile');
        }

        foreach ($patchfiles as $patchfile) {
            if (! file_exists($patchfile)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid patchfile; file %s does not exist',
                    $patchfile
                ));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $minor      = $input->getArgument('version');
        $patchfiles = $input->getOption('patchfile');

        $currentVersion = $this->detectVersion($minor, $output);

        // checkout release-$minor branch based on release-$currentVersion
        if (0 !== $this->exec(sprintf(
            'git checkout -b release-%s release-%s',
            $minor,
            $currentVersion
        ), $output)) {
            $output->writeln(sprintf(
                '<error>[ERROR] Could not create new branch release-%s based on tag release-%s!</error>',
                $minor,
                $currentVersion
            ));

            return;
        }

        // apply patchfile
        foreach ($patchfiles as $patchfile) {
            if (0 !== $this->exec(sprintf('git am < %s', $patchfile), $output)) {
                $output->writeln(sprintf(
                    '<error>[ERROR] Could not cleanly apply patchfile "%s"!</error>',
                    $patchfile
                ));

                return;
            }
        }

        // Create message for release
        $message = $this->getCommitMessages($currentVersion);
        if (false === $message) {
            $output->writeln('<error>[ERROR] Could not retrieve patch messages!</error>');

            return;
        }

        $nextVersion = $this->incrementVersion($currentVersion);

        // Update VERSION constant
        $this->updateVersionConstant($nextVersion);

        // Update README.md file
        $this->updateReadme($nextVersion);

        // Update CHANGELOG.md file
        $this->updateChangelog($nextVersion, $message);

        // Commit version information
        $this->commitVersionBump($nextVersion, $output);

        $message = sprintf(
            "Zend Framework %s\n\n%s",
            $nextVersion,
            $message
        );

        $output->writeln('<info>[DONE] Please verify the patch, and then execute:</info>');
        $output->writeln(sprintf(
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
     * @param OutputInterface $output
     * @return string
     */
    private function detectVersion($minor, OutputInterface $output)
    {
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
     * Retrieve a formatted list of commit messages to use for the tag message.
     *
     * @param string $startVersion
     * @return false|string
     */
    private function getCommitMessages($startVersion)
    {
        exec(
            sprintf('git log --oneline release-%s..HEAD', $startVersion),
            $output,
            $return
        );

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
     * @param OutputInterface $output
     */
    private function commitVersionBump($version, OutputInterface $output)
    {
        if (0 !== $this->exec(sprintf('git commit -a -m "Prepare for %s"', $version), $output)) {
            $output->writeln('<error>[ERROR] Could not commit version bump changes!</error>');
        }
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
}
