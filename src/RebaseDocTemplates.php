<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright https://github.com/zendframework/maintainers/blob/master/COPYRIGHT.md
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebaseDocTemplates extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Rebase documentation templates for the package')
            ->setHelp(
                'Rebase all documentation templates for the package specified.'
                . PHP_EOL . PHP_EOL
                . 'The script executes the following actions:' . PHP_EOL
                . '  - renames documentation directory: doc -> docs (or creates it if it does not exist)' . PHP_EOL
                . '  - places all supporting files into docs/ (github templates)' . PHP_EOL
                . '  - updates links to online documentation to point to docs.zendframework.com' . PHP_EOL
                . '  - updates coveralls configuration' . PHP_EOL
                . '  - updates .gitattributes and .gitignore definitions' . PHP_EOL
                . '  - creates Travis CI configuration if does not exist' . PHP_EOL
                . '  - updates LICENSE.md year range; creates file if it does not exist' . PHP_EOL
                . '  - updates COPYRIGHT.md year range; creates file if it does not exist' . PHP_EOL
                . '  - updates documentation configuration (copyright year and docs/ dir)' . PHP_EOL
                . '  - updates composer.json skeleton:' . PHP_EOL
                . '    - checks and fixes repository description (only for zendframework org)' . PHP_EOL
                . '    - checks and fixes order of sections' . PHP_EOL
                . '    - removes default type library' . PHP_EOL
                . '    - updates scripts' . PHP_EOL
                . '    - updates license' . PHP_EOL
                . '    - updates support links' . PHP_EOL
                . '    - updates "config" (adds sort-packages flag)' . PHP_EOL
                . '    - removes "minimum-stability" and "prefer-stable" keys' . PHP_EOL
                . '    - checks keywords' . PHP_EOL
                . '    - checks branch aliases'
            )
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path to the package; if not specified, assumed to be the current working directory',
                realpath(getcwd())
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $path = $input->getArgument('path');
        if (! is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid package path provided; directory "%s" does not exist',
                $path
            ));
        }

        if (! file_exists(sprintf('%s/composer.json', $path))) {
            throw new InvalidArgumentException(sprintf(
                'Cannot locate composer.json file in directory "%s"',
                $path
            ));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        chdir($path);

        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        list($org, $repo) = $this->getOrg();

        $replacement = [
            '{org}' => $org,
            '{repo}' => $repo,
            '{category}' => strpos($repo, 'zf-') === 0
                ? 'apigility'
                : (strpos($repo, 'zend-expressive') === 0
                    ? 'expressive'
                    : 'components'),
        ];

        // check if the repository has the documentation
        $hasDocs = file_exists('mkdocs.yml');

        $this->updateDocs($replacement);
        $this->updateCoveralls();
        $this->updateTravisCI();
        $this->updateGitAttributes($hasDocs);
        $this->updateGitIgnore($hasDocs);
        $this->updateComposerJson($errOutput, $hasDocs, $replacement, $org, $repo);
        $yearReplacement = $this->updateLicense($errOutput);
        if ($hasDocs) {
            $this->updateMkDocs($yearReplacement);
        }
        $this->updateCopyright($yearReplacement);
        $this->updateReadme($replacement);
        $this->updateDocsFiles();

        return 0;
    }

    private function updateDocs(array $replacement)
    {
        // if there is no docs directory create one
        if (! is_dir('docs')) {
            // if there is doc directory rename it to docs
            if (is_dir('doc')) {
                exec('git mv doc docs');
            } else {
                mkdir('docs', 0775);
            }
        }

        $docs = [
            'CODE_OF_CONDUCT.md',
            'CONTRIBUTING.md',
            'ISSUE_TEMPLATE.md',
            'PULL_REQUEST_TEMPLATE.md',
            'SUPPORT.md',
        ];

        if (file_exists('CONTRIBUTING.md')) {
            exec('git mv CONTRIBUTING.md docs/CONTRIBUTING.md');
        }

        if (file_exists('CONDUCT.md')) {
            exec('git mv CONDUCT.md docs/CODE_OF_CONDUCT.md');
        }

        foreach ($docs as $file) {
            if (file_exists(sprintf('docs/%s', $file))) {
                unlink(sprintf('docs/%s', $file));
            }

            $content = file_get_contents(__DIR__ . '/../template/docs/' . $file);
            $content = strtr($content, $replacement);

            file_put_contents(sprintf('docs/%s', $file), $content);
        }
    }

    private function updateCoveralls()
    {
        copy(__DIR__ . '/../template/.coveralls.yml', '.coveralls.yml');
    }

    private function updateTravisCI()
    {
        if (! file_exists('.travis.yml')) {
            copy(__DIR__ . '/../template/.travis.yml', '.travis.yml');
        }
    }

    private function updateGitAttributes($hasDocs)
    {
        $content = preg_split(
            "/\r?\n|\r/",
            trim(file_get_contents(__DIR__ . '/../template/.gitattributes'))
        );

        if (! $hasDocs) {
            $content = array_diff($content, ['/mkdocs.yml export-ignore']);
        }

        // Benchmarks
        if (file_exists('phpbench.json')) {
            // the directory name with benchmarks is not consistent across repositories, we check then both
            if (is_dir('benchmark')) {
                $content[] = '/benchmark export-ignore';
            }
            if (is_dir('benchmarks')) {
                $content[] = '/benchmarks export-ignore';
            }
            $content[] = '/phpbench.json export-ignore';
        }

        // License docheader
        if (file_exists('.docheader')) {
            $composer = $this->getComposerContent();
            if (isset($composer['require-dev']['malukenho/docheader'])) {
                $content[] = '/.docheader export-ignore';
            } else {
                unlink('.docheader');
            }
        }

        natsort($content);
        file_put_contents('.gitattributes', implode("\n", $content) . "\n");
    }

    private function updateGitIgnore($hasDocs)
    {
        $content = preg_split(
            "/\r?\n|\r/",
            trim(file_get_contents(__DIR__ . '/../template/.gitignore'))
        );

        if (! $hasDocs) {
            $content = array_diff($content, [
                '/docs/html/',
                '/zf-mkdoc-theme.tgz',
                '/zf-mkdoc-theme/',
            ]);
        }

        file_put_contents('.gitignore', implode("\n", $content) . "\n");
    }

    private function updateComposerJson(OutputInterface $errOutput, $hasDocs, array $replacement, $org, $repo)
    {
        $sectionOrder = [
            'name',
            'description',
            'type',
            'license',
            'keywords',
            'support',
            'require',
            'require-dev',
            'provide',
            'conflict',
            'suggest',
            'autoload',
            'autoload-dev',
            'config',
            'extra',
            'bin',
            'scripts',
        ];

        $templateContent = json_decode(file_get_contents(__DIR__ . '/../template/composer.json'), true);

        $content = $this->getComposerContent();
        if (isset($content['type']) && $content['type'] === 'library') {
            unset($content['type']);
        }
        $content['license'] = $templateContent['license'];
        $content['support'] = $templateContent['support'];
        if (! $hasDocs) {
            unset($content['support']['docs']);
        }
        foreach ($content['support'] as &$supportLink) {
            $supportLink = strtr($supportLink, $replacement);
        }

        $content['config'] = $templateContent['config'];
        $content['scripts'] = $templateContent['scripts'];

        // add license-check script only when we use .docheader library
        if (file_exists('.docheader')) {
            array_unshift($content['scripts']['check'], '@license-check');
            $content['scripts']['license-check'] = 'docheader check src/ test/';
        }

        // check keywords - always we must have "zf" and "zendframework" keywords
        if (empty($content['keywords'])) {
            $errOutput->writeln('<error>Missing "keywords" in composer.json</error>');
        } else {
            $hasZf = false;
            $hasZendframework = false;
            foreach ($content['keywords'] as &$keyword) {
                if ($keyword === 'zf2') {
                    $keyword = 'zf';
                    $hasZf = true;
                } elseif ($keyword === 'zf') {
                    $hasZf = 'zf';
                } elseif ($keyword === 'zendframework') {
                    $hasZendframework = true;
                }
            }

            if (! $hasZendframework) {
                array_unshift($content['keywords'], 'zendframework');
            }
            if (! $hasZf) {
                array_unshift($content['keywords'], 'zf');
            }
        }

        unset($content['minimum-stability'], $content['prefer-stable']);

        if ($org === 'zendframework') {
            $list = json_decode(
                file_get_contents('https://docs.zendframework.com/zf-mkdoc-theme/scripts/zf-component-list.json'),
                true
            );

            $description = null;
            foreach ($list as $component) {
                if (strpos($component['url'], '/' . $repo . '/') !== false) {
                    $description = rtrim($component['description'], '.');
                    break;
                }
            }

            if ($description !== null) {
                $content['description'] = $description;
            }
        }

        // check branch-alias in composer
        // get last released version:
        if ($tag = exec('git ls-remote --tags origin')) {
            if (preg_match('#[/-](\d+\.\d+)\.\d+#', $tag, $m)) {
                $ver = explode('.', $m[1]);
                if ($ver[0] === '0') {
                    $m[1] = '1.0';
                    $ver[0] = '1';
                    $ver[1] = '0';
                }
                $content['extra']['branch-alias']['dev-master'] = $m[1] . '-dev';

                // check if there is develop branch
                if ($output = exec('git ls-remote --heads origin refs/heads/develop')) {
                    $ver[1]++;

                    $content['extra']['branch-alias']['dev-develop'] = implode('.', $ver) . '-dev';
                }
            }
        }

        // sort section in composer:
        uksort($content, function ($a, $b) use ($sectionOrder) {
            $ia = array_search($a, $sectionOrder, true);
            $ib = array_search($b, $sectionOrder, true);

            if ($ia === $ib) {
                return 0;
            }

            if ($ia === false) {
                return 1;
            }

            if ($ib === false) {
                return -1;
            }

            if ($ia < $ib) {
                return -1;
            }

            return 1;
        });

        file_put_contents(
            'composer.json',
            json_encode(
                $content,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );
    }

    private function updateLicense(OutputInterface $errOutput)
    {
        $year = $startYear = date('Y');
        if (file_exists('LICENSE.md')) {
            $content = file_get_contents('LICENSE.md');
            if (preg_match('/Copyright \(c\) (\d{4})/', $content, $m)) {
                $startYear = $m[1];
            } else {
                $errOutput->writeln(
                    '<error>Cannot match year or year range in current LICENSE.md file;'
                    . ' using current year only.</error>'
                );
            }
        }

        $yearReplacement = $startYear < $year ? sprintf('%s-%s', $startYear, $year) : $year;
        $content = file_get_contents(__DIR__ . '/../template/LICENSE.md');
        $content = str_replace('{year}', $yearReplacement, $content);
        file_put_contents('LICENSE.md', $content);

        return $yearReplacement;
    }

    private function updateCopyright($yearReplacement)
    {
        $content = file_get_contents(__DIR__ . '/../template/COPYRIGHT.md');
        $content = str_replace('{year}', $yearReplacement, $content);
        file_put_contents('COPYRIGHT.md', $content);
    }

    private function updateMkDocs($yearReplacement)
    {
        $file = 'mkdocs.yml';

        $content = file_get_contents($file);
        $content = preg_replace('/docs_dir:.*/', 'docs_dir: docs/book', $content);
        $content = preg_replace('/site_dir:.*/', 'site_dir: docs/html', $content);
        $content = preg_replace(
            '/Copyright \(c\) \d{4}(-\d{4})? /',
            'Copyright (c) ' . $yearReplacement . ' ',
            $content
        );
        file_put_contents($file, $content);
    }

    private function updateReadme(array $replacement)
    {
        $templateContent = file_get_contents(__DIR__ . '/../template/README.md');
        $buildBadge = null;
        if (preg_match('/\[\!\[Build Status\].*/', $templateContent, $m)) {
            $buildBadge = strtr($m[0], $replacement);
        }

        $coverageBadge = null;
        if (preg_match('/\[\!\[Coverage Status\].*/', $templateContent, $m)) {
            $coverageBadge = strtr($m[0], $replacement);
        }

        $file = 'README.md';
        $content = file_get_contents($file);

        if ($buildBadge) {
            $content = preg_replace('/\[\!\[Build Status\].*/', $buildBadge, $content, 1, $count);

            if (! $count) {
                fwrite(
                    STDERR,
                    sprintf(
                        'Missing Build Status badge in README.md; please add:%s%s%s',
                        PHP_EOL,
                        $buildBadge,
                        PHP_EOL
                    )
                );
            }
        }

        if ($coverageBadge) {
            $content = preg_replace('/\[\!\[Coverage Status\].*/', $coverageBadge, $content, 1, $count);

            if (! $count) {
                fwrite(
                    STDERR,
                    sprintf(
                        'Missing Coverage badge in README.md; please add:%s%s%s',
                        PHP_EOL,
                        $coverageBadge,
                        PHP_EOL
                    )
                );
            }
        }

        // replace link to the docs in README.md
        $content = str_replace('zendframework.github.io', 'docs.zendframework.com', $content);
        file_put_contents($file, $content);
    }

    private function updateDocsFiles()
    {
        if (is_dir('docs/book/')) {
            $dir = new RecursiveDirectoryIterator('docs/book/');
            $iterator = new RecursiveIteratorIterator($dir);
            $regex = new RegexIterator($iterator, '/^.+\.(md|html)$/', RecursiveRegexIterator::GET_MATCH);

            foreach ($regex as $file) {
                $file = $file[0];
                $content = file_get_contents($file);
                $content = str_replace('zendframework.github.io', 'docs.zendframework.com', $content);
                $content = preg_replace('/[ \t\r]+$/m', '', $content);
                file_put_contents($file, $content);
            }
        }
    }

    private function getOrg()
    {
        $composer = $this->getComposerContent();
        if (! isset($composer['name']) || ! preg_match('/^([a-z0-9-]+)\/([a-z0-9-]+)$/', $composer['name'], $m)) {
            throw new RuntimeException(
                'Cannot extract repository name from composer.json, please check value of "name" key in that file.'
            );
        }

        return [$m[1], $m[2]];
    }

    private function getComposerContent()
    {
        return json_decode(file_get_contents('composer.json'), true);
    }
}
