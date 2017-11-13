<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePackage extends Command
{
    protected function configure()
    {
        $this
            ->setName('create-package')
            ->setDescription('Creates a new package')
            ->setHelp('here is the help...')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the package to create');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $name = $input->getArgument('name');
        if (! preg_match('/^(zend|zf)-[a-z0-9-]+$/', $name)) {
            throw new \InvalidArgumentException(
                'Invalid package name, must be prefixed with "zend-" or "zf-"'
                . ' and can contain only lowercase letters, numbers and dash.'
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $input->getArgument('name');

        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if (is_dir($repo)) {
            $errOutput->writeln(sprintf(
                '<error>Directory with name "%s" already exists</error>',
                $repo
            ));

            return;
        }

        if (! mkdir($repo, 0775) && ! is_dir($repo)) {
            $errOutput->writeln(sprintf(
                '<error>Cannot create package directory "%s"</error>',
                $repo
            ));

            return;
        }

        $namespace = $this->determineNamespace($input, $output, $repo);

        $replacement = [
            '{org}' => $this->getOrg($repo),
            '{repo}' => $repo,
            '{category}' => $this->getCategory($repo),
            '{namespace}' => $namespace,
            '{namespace-test}' => preg_replace('/^(Zend|ZF)/', '\\1Test', $namespace),
            '{year}' => date('Y'),
        ];

        $this->copyFiles(__DIR__ . '/../template/', $repo . '/', $replacement);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Would you like to run composer and create initial commit in the new package? [<info>Y/n</info>] '
        );

        if ($helper->ask($input, $output, $question)) {
            exec(sprintf(
                'cd %s && composer update && git init . && git add . && git commit -am "Initial creation"',
                $repo
            ));
        }
    }

    private function determineNamespace(InputInterface $input, OutputInterface $output, $repo)
    {
        $exp = explode('-', $repo);

        $namespace = array_shift($exp) === 'zend' ? 'Zend' : 'ZF';

        if ($exp[0] === 'expressive') {
            $namespace .= '\Expressive';
            array_shift($exp);
        }

        if (count($exp) === 1) {
            $namespace .= '\\' . ucfirst(array_shift($exp));
        }

        $namespaces = [];
        if ($exp) {
            $base = ucwords(implode(' ', $exp));
            $namespaces[] = $namespace . '\\' . str_replace(' ', '\\', $base);
            $namespaces[] = $namespace . '\\' . str_replace(' ', '', $base);
        } else {
            $namespaces[] = $namespace;
        }
        $namespaces['c'] = 'custom';

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'What namespace would you like to use?',
            $namespaces,
            0
        );
        $question->setErrorMessage('Invalid choice "%s". Please try again.');

        $answer = $helper->ask($input, $output, $question);

        if ($answer !== 'c') {
            return $answer;
        }

        $question = new Question('Custom namespace: ');
        $question->setValidator(function ($value) use ($repo) {
            $firstPart = strpos($repo, 'zf') === 0 ? 'ZF' : 'Zend';
            if (! preg_match('/^' . $firstPart . '(\\[A-Z][a-z0-9]*)+$/', $value)) {
                throw new RuntimeException(sprintf(
                    'Invalid namespace provided: %s',
                    $value
                ));
            }

            // must be consistent with package name
            if (strtolower(str_replace('\\', '', $value))
                !== strtolower(str_replace('-', '', $repo))
            ) {
                throw new RuntimeException('Namespace have to be consistent with package name');
            }

            return $value;
        });

        return $helper->ask($input, $output, $question);
    }

    private function copyFiles($src, $dest, array $replacement)
    {
        $dir = new RecursiveDirectoryIterator($src);
        $iterator = new RecursiveIteratorIterator($dir);

        $replacementJson = $replacement;
        $replacementJson['{namespace}'] = str_replace('\\', '\\\\', $replacement['{namespace}']);
        $replacementJson['{namespace-test}'] = str_replace('\\', '\\\\', $replacement['{namespace-test}']);

        /** @var \RecursiveDirectoryIterator $iterator */
        $iterator->rewind();
        while ($iterator->valid()) {
            if (! $iterator->isDot()) {
                $file = $iterator->getSubPathname();
                $dirname = dirname($dest . $file);
                if (! is_dir($dirname)) {
                    mkdir($dirname, 0775, true);
                }

                if ($iterator->isLink()) {
                    symlink(readlink($src . $file), $dest . $file);
                } else {
                    $content = file_get_contents($src . $file);
                    $content = strtr($content, $iterator->getExtension() === 'json' ? $replacementJson : $replacement);
                    file_put_contents($dest . $file, $content);
                }
            }

            $iterator->next();
        }
    }

    private function getOrg($repo)
    {
        if (strpos('zf-', $repo)) {
            return 'zfcampus';
        }

        return 'zendframework';
    }

    private function getCategory($repo)
    {
        if (strpos('zf-', $repo)) {
            return 'apigility';
        }

        if (strpos('zend-expressive', $repo)) {
            return 'expressive';
        }

        return 'components';
    }
}
