<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer;

use InvalidArgumentException;
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
            ->setHelp(
                'The new package will be created in the directory of provided package name.'
                . ' The package will be initialized with the whole basic structure.'
                . ' It will be possible to choose namespace, based on provided package name,'
                . ' or provide another namespace. At the end of the process it is possible to'
                . ' create initial commit with the whole package structure.'
                . PHP_EOL . PHP_EOL
                . 'Package is created with documentation templates, all support files,'
                . ' QA tools (CodeSniffer and PHPUnit), default ConfigProvider with tests,'
                . ' Travis CI and coveralls configuration.'
                . PHP_EOL . PHP_EOL
                . 'Package name can be provided with the organization in format org/name.'
                . ' When organization is not specified explicitly the script will try to'
                . ' detect it based on the name. For names starting from "zend-" and "zf-"'
                . ' organizations "zendframework" and "zfcampus" will be used respectively.'
            )
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the package to create; can be provided with organization name: org/name'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $name = $input->getArgument('name');
        if (! preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)?$#', $name)) {
            throw new InvalidArgumentException(
                'Invalid package name, can contain only lowercase letters, numbers and dash.'
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        list($org, $repo) = $this->getOrg($name);

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
            '{org}' => $org,
            '{repo}' => $repo,
            '{category}' => $this->getCategory($repo),
            '{namespace}' => $namespace,
            '{namespace-test}' => $this->getTestNamespace($namespace),
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

    private function getTestNamespace($namespace)
    {
        $parts = explode('\\', $namespace);
        $parts[0] .= 'Test';

        return implode('\\', $parts);
    }

    private function determineNamespace(InputInterface $input, OutputInterface $output, $repo)
    {
        $exp = explode('-', $repo);

        if ($exp[0] === 'zf') {
            $exp[0] = 'ZF';
        }

        $namespaces = [
            str_replace(' ', '\\', ucwords(implode(' ', $exp))),
            'c' => 'custom',
        ];

        $helper = $this->getHelper('question');

        $question = new ChoiceQuestion(
            'What namespace would you like to use?',
            $namespaces,
            0
        );
        $question->setErrorMessage('Invalid choice "%s". Please try again.');

        $answer = $helper->ask($input, $output, $question);

        if ($answer !== 'c') {
            return $namespaces[$answer];
        }

        $question = new Question('Custom namespace: ');
        $question->setValidator(function ($value) use ($repo) {
            if (strpos($repo, 'zf-') === 0 || strpos($repo, 'zend-') === 0) {
                if (! preg_match('/^(ZF|Zend)(\\\\[A-Z][a-zA-Z0-9]*)+$/', $value)) {
                    throw new RuntimeException(sprintf(
                        'Invalid namespace provided: %s',
                        $value
                    ));
                }
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
        if (false !== strpos($repo, '/')) {
            return explode('/', $repo, 2);
        }

        if (! preg_match('/^(?P<type>zend|zf)-/', $repo, $matches)) {
            throw new RuntimeException('Missing organization in package name');
        }

        switch ($matches['type']) {
            case 'zend':
                return ['zendframework', $repo];
            case 'zf':
                return ['zfcampus', $repo];
        }
    }

    private function getCategory($repo)
    {
        if (strpos('zf-', $repo)) {
            return 'apigility';
        }

        if (strpos('-expressive-', $repo)) {
            return 'expressive';
        }

        return 'components';
    }
}
