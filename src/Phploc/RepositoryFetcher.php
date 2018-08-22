<?php
/**
 * @see       https://github.com/zendframework/maintainers for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/maintainers/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZF\Maintainer\Phploc;

use Closure;
use Github\Client;
use RuntimeException;

class RepositoryFetcher
{
    /**
     * @todo Add zf1, zf1-extras to this list 2018-01-01, as Zend Server support
     *     for ZF1 ends the day previous.
     * @var string[]
     */
    private const REPO_BLOCKLIST = [
        'zendframework/zendframework', // metapackage
        'zendframework/zendframework.github.io',
        'zendframework/zend-coding-standard',
        'zendframework/zend-mvc-form', // metapackage
        'zendframework/zend-mvc-plugins', // metapackage
        'zendframework/zf2-documentation',
        'zendframework/zf2-tutorial',
        'zendframework/zf3-web',
        'zendframework/zfbot',
        'zendframework/zf-composer-repository',
        'zendframework/zf-mkdoc-theme',
        'zendframework/zf-web',
        'zfcampus/zendcon-design-patterns',
        'zfcampus/zf-angular',
        'zfcampus/zf-apigility-example',
        'zfcampus/zf-apigility-welcome',
    ];

    /**
     * @var string[]
     */
    private const REPO_ACCEPTLIST = [
        'zendframework/ZendService_Amazon',
        'zendframework/ZendService_Apple_Apns',
        'zendframework/ZendService_Google_Gcm',
        'zendframework/ZendService_ReCaptcha',
        'zendframework/ZendService_Twitter',
        'zendframework/ZendSkeletonApplication',
        'zendframework/ZendXml',
    ];

    /**
     * Arbitrary start cursor for paginated results.
     *
     * @var string
     */
    private const GRAPHQL_CURSOR_START = 'Y3Vyc29yOjEwMA==';

    /**
     * Organizations we will query.
     *
     * @var string[]
     */
    private const GRAPHQL_ORGANIZATIONS = [
        'zendframework',
        'zfcampus',
    ];

    /**
     * Query for fetching all repositories from GitHub.
     *
     * The two ids provided are the GitHub unique identifiers for the zendframework
     * and zfcampus organizations, respectively.
     *
     * The "after" string is for providing an initial cursoer so that we can page
     * through results in order to retrieve all information.
     *
     * @var string
     */
    private const GRAPHQL_QUERY = <<< 'EOT'
query tagsByOrganization(
  $organization: String!
  $cursor: String!
) {
  organization(login: $organization) {
    repositories(first: 100, after: $cursor) {
      pageInfo {
        startCursor
        hasNextPage
        endCursor
      }
      nodes {
        nameWithOwner
      }
    }
  }
}
EOT;

    /**
     * @var callable[]
     */
    private $criteria;

    public function __construct()
    {
        $this->criteria = [
            Closure::fromCallable([$this, 'filterBlockList']),
            Closure::fromCallable([$this, 'filterAcceptList']),
        ];
    }

    public function execute(Client $client) : iterable
    {
        $data = [];

        foreach (self::GRAPHQL_ORGANIZATIONS as $organization) {
            $cursor = self::GRAPHQL_CURSOR_START;

            do {
                $results = $client->api('graphql')->execute(self::GRAPHQL_QUERY, [
                    'organization' => $organization,
                    'cursor' => $cursor,
                ]);

                if (isset($results['errors'])) {
                    throw new RuntimeException(sprintf(
                        'Error fetching repositories: %s',
                        $results['errors']['message']
                    ));
                }

                $data = array_merge($data, $results['data']['organization']['repositories']['nodes']);

                $cursor = $this->getNextCursorFromResults($results);
            } while ($cursor);
        }

        return $this->processData($data);
    }

    private function getNextCursorFromResults(array $results) : ?string
    {
        // @codingStandardsIgnoreStart
        return isset($results['data']['organization']['repositories']['pageInfo']['hasNextPage'])
            ? $results['data']['organization']['repositories']['pageInfo']['endCursor']
            : null;
        // @codingStandardsIgnoreEnd
    }

    /**
     * @return string[]
     */
    private function processData(array $data) : iterable
    {
        $repos = array_filter(array_column($data, 'nameWithOwner'), function ($repository) {
            foreach ($this->criteria as $criteria) {
                if (! $criteria($repository)) {
                    return false;
                }
            }
            return true;
        });
        natsort($repos);
        return $repos;
    }

    private function filterBlockList(string $name) : bool
    {
        return ! in_array($name, self::REPO_BLOCKLIST, true);
    }

    private function filterAcceptList(string $name) : bool
    {
        [$org, $repo] = explode('/', $name, 2);
        return in_array($name, self::REPO_ACCEPTLIST, true)
            || 'z' === $repo[0];
    }
}
