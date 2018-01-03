<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Maintainer\Sync;

use Github\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Repos extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Synchronize zendframework packages descriptions')
            ->setHelp(
                'Synchronize descriptions of zendframework organization packages with list'
                . ' from file https://docs.zendframework.com/zf-mkdoc-theme/scripts/zf-component-list.json'
            )
            ->addArgument(
                'token',
                InputArgument::REQUIRED,
                'GitHub access token'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token = $input->getArgument('token');

        $list = json_decode(
            file_get_contents('https://docs.zendframework.com/zf-mkdoc-theme/scripts/zf-component-list.json'),
            true
        );

        $client = new Client();
        $client->authenticate($token, null, $client::AUTH_URL_TOKEN);

        foreach ($list as $comp) {
            $name = basename($comp['url']);

            $output->write(sprintf('Updating repository %s... ', $name));
            try {
                $client->repo()->update('zendframework', $name, [
                    'name'         => $name,
                    'description'  => $comp['description'],
                    'homepage'     => $comp['url'],
                    'has_wiki'     => false,
                ]);
                $output->writeln('<info>DONE</info>');
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>FAILED</error>%s    %s', PHP_EOL, $e->getMessage()));
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>FAILED</error>%s    %s', PHP_EOL, $e->getMessage()));
            }
        }

        return 0;
    }
}
