# CONTRIBUTING to Zend Framework Repositories

This documentation outlines the general procedure for contributing to the
various Zend Framework components and repositories.

## Filing Issue Reports

Before you file an issue report, we ask that you ensure that what you have is indeed a bug, by
doing a little due-diligence:

- See if your use case is covered in the manual.
- See if your use case is demonstrated by the API docs.
- Ask the mailing list about your particular use case.
- Discuss your issue with someone in one of the IRC channels: `#zftalk` (general help) or
  `#zftalk.dev` (contributors) on [Freenode IRC](http://freenode.net).
- Write a failing test case.

None of these are required steps. However, failure to do any of them can and likely will result in
closing of your issue, due to an inability to reproduce and/or understand the problem. The best
possible outcome is to provide the minimal amount of code required to reproduce the issue, along
with details of what you expect to happen, and what you observe. These can easily be translated to
unit tests, which will allow us to both diagnose and resolve your issue sooner.

One you have all the necessary details ready, submit to the issue tracker for the appropriate
component; if you have written a failing unit test, you can send a pull request with just the
failing unit test instead. See the [component list](#component-list) below for links.

Be aware that acceptable resolutions might include:

- the documented behavior is incorrect (in such cases, we will correct the documentation).
- the observed behavior is _expected_.

## Reporting Potential Security Issues

If you have encountered a potential security vulnerability, please **DO NOT** report it on the public
issue trackers: send it to us at [zf-security@zend.com](mailto:zf-security@zend.com) instead.
We will work with you to verify the vulnerability and patch it as soon as possible.

When reporting issues, please provide the following information:

- Component(s) affected.
- A description indicating how to reproduce the issue.
- A summary of the security vulnerability and impact.

We request that you contact us via the email address above and give the project contributors a
chance to resolve the vulnerability and issue a new release prior to any public exposure; this helps
protect users and provides them with a chance to upgrade and/or update in order to protect their
applications.

For sensitive email communications, please use [our PGP key](http://framework.zend.com/zf-security-pgp-key.asc).

## Contributing Bugfixes or Features

By and large, this framework is built and maintained by the community. This means that process for
submitting something back, be it a patch, some documentation, or new feature requires some level of
community interactions.

Committing code is easy:

- Fork the appropriate component repository (see the [component list](#component-list) below for
  links).
- Create a local development branch for the bugfix; we recommend naming the branch such that you'll
  recognize its purpose: `hotfix/mail-header-parsing`, `feature/yaml-serialization`, etc.
- Commit a change, and push your local branch to your github fork.
  ```console
  $ git commit
  $ git push yourreponame branchname:branchname
  ```
- Send us a pull request for your changes to be included (see the [component list](#component-list)
  below for links).

For more details, see the [Recommended Workflow for Contributions](#recommended-workflow-for-contributions)
section below.

## CODING STANDARDS

- [Zend Framework 1](http://framework.zend.com/manual/1.12/en/coding-standard.html)
- [Zend Framework 2](CODING_STANDARDS.md)

Projects either use [php-cs-fixer](http://cs.sensiolabs.org/) or
[phpcs](https://github.com/squizlabs/PHP_CodeSniffer) to validate them; both tools offer
functionality for fixing most violations.

### php-cs-fixer

Components using [php-cs-fixer](http://cs.sensiolabs.org/) will contain a `.php_cs` file that
configures the tool with our ruleset.

To validate only, run it in `--dry-run` mode:

```console
$ ./vendor/bin/php-cs-fixer fix -v --diff --dry-run --config-file=.php_cs
```

This will give you a detailed report of any violations, along with suggested fixes (via diffs).

To fix violoations, run without the `--dry-run` flag:

```console
$ ./vendor/bin/php-cs-fixer fix -v --diff --config-file=.php_cs
```

This will correct most issues. Be sure to run unit tests, and, if successful, add and commit your
files on completion:

```console
$ ./vendor/bin/phpunit
$ git commit -a -m 'CS fixes as performed by php-cs-fixer'
```
### phpcs

Projects using [phpcs](https://github.com/squizlabs/PHP_CodeSniffer) for coding standards can either
be run using the `--standard=PSR2` flag, or, if a `phpcs.xml` file is present, without any flags:

```console
# If no phpcs.xml file is present, run the tool over the src and test dirs:
$ ./vendor/bin/phpcs --standard=PSR2 src test
# If a phpcs.xml file is present, no arguments are necessary:
$ ./vendor/bin/phpcs
```

`phpcs` also provides a tool for fixing discovered errors, `phpcbf`; it can be invoked with exactly
the same arguments as `phpcs`:

```console
# If no phpcs.xml file is present, run the tool over the src and test dirs:
$ ./vendor/bin/phpcbf --standard=PSR2 src test
# If a phpcs.xml file is present, no arguments are necessary:
$ ./vendor/bin/phpcbf
```

This will correct most issues. Be sure to run unit tests, and, if successful, add and commit your
files on completion:

```console
$ ./vendor/bin/phpunit
$ git commit -a -m 'CS fixes as performed by php-cs-fixer'
```
## RUNNING TESTS

> ### Note: testing versions prior to 2.4
>
> This component originates with Zend Framework 2. During the lifetime of ZF2,
> testing infrastructure migrated from PHPUnit 3 to PHPUnit 4. In most cases, no
> changes were necessary. However, due to the migration, tests may not run on
> versions < 2.4. As such, you may need to change the PHPUnit dependency if
> attempting a fix on such a version.

To run tests:

- Clone the repository:

  ```console
  $ git clone git@github.com:zendframework/zend-authentication.git
  $ cd
  ```

- Install dependencies via composer:

  ```console
  $ curl -sS https://getcomposer.org/installer | php --
  $ ./composer.phar install
  ```

  If you don't have `curl` installed, you can also download `composer.phar` from https://getcomposer.org/

- Run the tests via `phpunit` and the provided PHPUnit config, like in this example:

  ```console
  $ ./vendor/bin/phpunit
  ```

You can turn on conditional tests with the phpunit.xml file.
To do so:

 -  Copy `phpunit.xml.dist` file to `phpunit.xml`
 -  Edit `phpunit.xml` to enable any specific functionality you
    want to test, as well as to provide test values to utilize.

## Recommended Workflow for Contributions

Your first step is to establish a public repository from which we can
pull your work into the master repository. We recommend using
[GitHub](https://github.com), as that is where the component is already hosted.

1. Setup a [GitHub account](http://github.com/), if you haven't yet
2. Fork the repository (http://github.com/zendframework/zend-authentication)
3. Clone the canonical repository locally and enter it.

   ```console
   $ git clone git://github.com:zendframework/zend-authentication.git
   $ cd zend-authentication
   ```

4. Add a remote to your fork; substitute your GitHub username in the command
   below.

   ```console
   $ git remote add {username} git@github.com:{username}/zend-authentication.git
   $ git fetch {username}
   ```

### Keeping Up-to-Date

Periodically, you should update your fork or personal repository to
match the canonical ZF repository. Assuming you have setup your local repository
per the instructions above, you can do the following:


```console
$ git checkout master
$ git fetch origin
$ git rebase origin/master
# OPTIONALLY, to keep your remote up-to-date -
$ git push {username} master:master
```

If you're tracking other branches -- for example, the "develop" branch, where
new feature development occurs -- you'll want to do the same operations for that
branch; simply substitute  "develop" for "master".

### Working on a patch

We recommend you do each new feature or bugfix in a new branch. This simplifies
the task of code review as well as the task of merging your changes into the
canonical repository.

A typical workflow will then consist of the following:

1. Create a new local branch based off either your master or develop branch.
2. Switch to your new local branch. (This step can be combined with the
   previous step with the use of `git checkout -b`.)
3. Do some work, commit, repeat as necessary.
4. Push the local branch to your remote repository.
5. Send a pull request.

The mechanics of this process are actually quite trivial. Below, we will
create a branch for fixing an issue in the tracker.

```console
$ git checkout -b hotfix/9295
Switched to a new branch 'hotfix/9295'
```

... do some work ...


```console
$ git commit
```

... write your log message ...


```console
$ git push {username} hotfix/9295:hotfix/9295
Counting objects: 38, done.
Delta compression using up to 2 threads.
Compression objects: 100% (18/18), done.
Writing objects: 100% (20/20), 8.19KiB, done.
Total 20 (delta 12), reused 0 (delta 0)
To ssh://git@github.com/{username}/zend-authentication.git
   b5583aa..4f51698  HEAD -> master
```

To send a pull request, you have two options.

If using GitHub, you can do the pull request from there. Navigate to
your repository, select the branch you just created, and then select the
"Pull Request" button in the upper right. Select the user/organization
"zendframework" as the recipient.

If using your own repository - or even if using GitHub - you can use `git
format-patch` to create a patchset for us to apply; in fact, this is
**recommended** for security-related patches. If you use `format-patch`, please
send the patches as attachments to:

-  zf-devteam@zend.com for patches without security implications
-  zf-security@zend.com for security patches

#### What branch to issue the pull request against?

Which branch should you issue a pull request against?

- For fixes against the stable release, issue the pull request against the
  "master" branch.
- For new features, or fixes that introduce new elements to the public API (such
  as new public methods or properties), issue the pull request against the
  "develop" branch.

### Branch Cleanup

As you might imagine, if you are a frequent contributor, you'll start to
get a ton of branches both locally and on your remote.

Once you know that your changes have been accepted to the master
repository, we suggest doing some cleanup of these branches.

-  Local branch cleanup

   ```console
   $ git branch -d <branchname>
   ```

-  Remote branch removal

   ```console
   $ git push {username} :<branchname>
   ```

## RESOURCES

-  ZF Contributor's mailing list:
   Archives: http://zend-framework-community.634137.n4.nabble.com/ZF-Contributor-f680267.html
   Subscribe: zf-contributors-subscribe@lists.zend.com
-  ZF Contributor's IRC channel:
   #zftalk.dev on Freenode.net

If you are working on new features or refactoring [create a proposal](https://github.com/zendframework/zend-authentication/issues/new).
