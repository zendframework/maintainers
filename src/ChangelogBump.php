<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create a new branch and create a new changelog version entry.
 */
class ChangelogBump extends Command
{
    protected function configure()
    {
        $this
            ->setName('changelog-bump')
            ->setDescription('Bumps the CHANGELOG version for the current component')
            ->setHelp(
                'Checkout a temporary version/bump branch based on --base|-b'
                . ' (defaults to master), and create a new CHANGELOG entry for the provided version.'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'New CHANGELOG version to add'
            )
            ->addOption(
                'base',
                'b',
                InputOption::VALUE_REQUIRED,
                'Branch against which to change the CHANGELOG version; can be one of "master" or "develop".',
                'master'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $base = $input->getOption('base');
        if (! in_array($base, ['master', 'develop'], true)) {
            throw new InvalidArgumentException(
                'Invalid base branch provided; "master" and "develop" are only allowed'
            );
        }

        $version = $input->getArgument('version');
        if (! preg_match('/^(0|[1-9]\d*)\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException(
                'Invalid version provided'
            );
        }
    }

    /**
     * Create a new local branch and bump the changelog version entry.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getArgument('version');
        $base = $input->getOption('base');

        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        // checkout version/bump branch based on $base
        if (0 !== $this->exec(sprintf('git checkout -b version/bump %s', $base), $output)) {
            $errOutput->writeln(sprintf(
                '<error>Could not create new version/bump branch based on branch %s!</error>',
                $base
            ));

            return;
        }

        // Update CHANGELOG.md file
        $this->updateChangelog($version);

        // Commit version bump
        $this->commitVersionBump($version, $output, $errOutput);

        $message = sprintf('<info>Please verify and merge the branch back to %s', $base);
        if ($base === 'master') {
            $message .= ' as well as develop';
        }
        $message .= '</info>';

        $output->writeln($message);
        $output->writeln('Once done merging, remove this branch using:');
        $output->writeln('    <comment>git branch -d version/bump</comment>');
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
     * @param OutputInterface $output
     * @param OutputInterface $errOutput
     */
    private function commitVersionBump($version, OutputInterface $output, OutputInterface $errOutput)
    {
        if (0 !== $this->exec(sprintf('git commit -a -m "Bumped to next dev version (%s)"', $version), $output)) {
            $errOutput->writeln('<error>Could not commit version bump changes!</error>');
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
