# Maintainer/Contributor Guidelines for Zend Framework

This is a repository containing information and tools for contributors to and maintainers of Zend
Framework.

- [Contributors Guide](CONTRIBUTORS.md)
- [Maintainers Guide](MAINTAINERS.md)
- [Long Term Support Workflow for Maintainers](LTS-WORKFLOW.md)

For documentation guidelines, visit the [documentation repository](https://github.com/zendframework/documentation/blob/master/CONTRIBUTING.md).

## Templates

The `template/` directory contains templates for use in creating new
repositories, including:

- a `.coveralls.yml` file with common settings.
- a `.gitattributes` file with common settings.
- a `.gitignore` file with common exclusions.
- a `.travis.yml` template with recommended configuration for new components.
- a `CHANGELOG.md` template with library changelog.
- a `composer.json` with a basic package structure.
- a `LICENSE.md` template.
- a `mkdocs.yml` file with MkDocs configuration.
- a `phpcs.xml` file with zend coding standard configuration.
- a `phpunit.xml.dist§ file with PHPUnit configuration.
- a `README.md` template for new components with basic structure.
- a `docs/` directory with support templates:
  - `CODE_OF_CONDUCT.md` with the Code Manifesto.
  - `CONTRIBUTING.md` with instructions on how to contribute.
  - `ISSUE_TEMPLATE.md` for new issues.
  - `PULL_REQUEST_TEMPLATE.md` for new pull requests.
  - `SUPPORT.md` with instructions on how to get support for the package.
- a `docs/book/` directory with documentation template.
- a `src/` directory with `ConfigProvider` template.
- a `test/` directory with `ConfigProviderTest` test case.

Templates all use the same placeholders, which can be replaced en masse:

- `{year}` is the year of first publication, or a range of years from first
  publication until most recent update.
- `{org}` refers to the github organization under which the package will live.
- `{repo}` is the name of the repository.
- `{category}` should be one of `expressive`, `components`, or `apigility`.
- `{namespace}` should be the top-most namespace unique to the repository.
- `{namespace-test}` should be the namespace used for unit tests in the repository.

## Maintainer tools

CLI tools for automating several tasks, particularly the LTS workflows, are present in this
repository. Run the following:

```console
$ composer install
```

to ensure dependencies are present. The tools are in the `bin/zf-maintainer` script.

This script currently has the following general use commands:

- `changelog-bump <version> [-b|--base <master|develop>]`: bumps the
  CHANGELOG version for the current component.
- `create-package <name>`: creates a new package from templates.
- `rebase-doc-templates [<path>]`: rebases all templates in package;
  by default, the current directory is used.
- `sync-repos <github-token>`: synchronizes zendframework package descriptions.

and the following commands to help maintain LTS releases:

- `lts:components`: lists LTS components, one per line.
- `lts:patch -p|--patchfile=<patchfile> -t|--target=<target> -c|--component=<component>`:
  rewrites a component patch so it may be applied against the ZF2 repo.
- `lts:release <version> -e|--exclude=<exclude> [-b|--basePath <base-path>]`:
  tags a new LTS maintenance release of all components.
- `lts:stage <version> -p|--patchfile=<patchfile>`: stages a new LTS release
  by applying the given patchfile(s).

For detailed information about each command, call them with the `--help` flag,
or using the syntax `zf-maintainer help <command>`.
