<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZF\Maintainer;

use Github\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PhplocCommand extends Command
{
    protected function configure()
    {
        $this->setDescription('Calculate lines of code for the entire project');
        $this->setHelp(
            'Retrieves a list of all repositories under the zendframework and zfcampus'
            . ' organizations, performs a local checkout of the repository, and then runs'
            . ' phploc over each, reporting the non-comment lines of code for each. When'
            . ' complete, it also reports a sum total of all repositories.'
        );
        $this->addArgument(
            'token',
            InputArgument::REQUIRED,
            'GitHub OAuth2 token to use for retrieving the repository list.'
        );
        $this->addOption(
            'workdir',
            'w',
            InputOption::VALUE_REQUIRED,
            'Directory into which to perform git clones of each repository;'
                . ' defaults to current working directory.',
            getcwd()
        );
        $this->addOption(
            'phplocbin',
            'p',
            InputOption::VALUE_REQUIRED,
            'Name or path to phploc binary to use when performing phploc calculations.',
            'phploc'
        );
        $this->addOption(
            'gitbin',
            'g',
            InputOption::VALUE_REQUIRED,
            'Name or path to git binary to use to perform git cloning operations.',
            'git'
        );
        $this->addOption(
            'grepbin',
            'r',
            InputOption::VALUE_REQUIRED,
            'Name or path to grep binary to use when filtering phploc output.',
            'grep'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $token = $input->getArgument('token');
        $workdir = $input->getOption('workdir');
        $phploc = $input->getOption('phplocbin');
        $git = $input->getOption('gitbin');
        $grep = $input->getOption('grepbin');

        $output->writeln('<info>Fetching repository list...</info>');

        $client = new Client();
        $client->authenticate($token, Client::AUTH_HTTP_TOKEN);

        $gitRepo = new Phploc\GitRepo($git, $workdir);
        $counter = new Phploc\Phploc($phploc, $grep);

        $errors = 0;
        $counts = [];
        $total = 0;

        foreach ((new Phploc\RepositoryFetcher())->execute($client) as $repo) {
            $output->writeln(sprintf('<comment>%s</comment>', $repo));
            $output->write('  <info>Cloning repository...</info>');
            try {
                $path = $gitRepo->clone($repo);
            } catch (RuntimeException $e) {
                $output->writeln('<error>FAILED</error>');
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                $errors +=1;
                continue;
            }
            $output->writeln(sprintf('Done! Cloned to %s', $path));

            $output->write('  <info>Performing count...</info>');
            try {
                $count = $counter->count($path);
                $counts[$repo] = $count;
                $total += $count;
                $output->writeln(sprintf(' %d', $count));
            } catch (RuntimeException $e) {
                $output->writeln('<error>FAILED</error>');
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                $errors +=1;
            }

            $output->write('  <info>Deleting repository...</info>');
            $gitRepo->cleanup($path);
            $output->writeln(sprintf('Done!', $path));
        }

        $this->emitTable($input, $output, $total, $counts);

        if ($errors > 0) {
            $output->writeln('');
            $output->writeln(
                '<error>FINISHED, but one or more errors were reported. Check your output for details.</error>'
            );
            return 1;
        }

        return 0;
    }

    private function emitTable(InputInterface $input, OutputInterface $output, int $total, array $repos) : void
    {
        $table = [];

        foreach ($repos as $repo => $count) {
            $table[] = [$repo, $count];
        }

        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        $io->section('PHPLOC Results');
        $io->table(
            ['Repository', 'Count'],
            $table
        );
        $io->newLine();
        $output->writeln(sprintf('<info>Total LOC:</info> %d', $total));
    }
}
