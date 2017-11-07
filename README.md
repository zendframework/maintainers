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

- a `LICENSE.md` template; replace `{year}` with the year of creation and/or a
  range of dates from creation to latest updates.
- a `.travis.yml` template with recommended configuration for new components.
- a `docs/` directory with support templates:
  - `CODE_OF_CONDUCT.md` with the Code Manifesto.
  - `CONTRIBUTING.md` with instructions on how to contribute; replace the
    placeholders `{org}`, `{repo}` with appropriate values.
  - `ISSUE_TEMPLATE.md` for new issues; replace the placeholders `{org}` and
    `{repo}` with appropriate values.
  - `PULL_REQUEST_TEMPLATE.md` for new pull requests.
  - `SUPPORT.md` with instructions on how to get support for the package;
    replace the placeholders `{org}`, `{repo}` with appropriate values.

## Maintainer tools

CLI tools for automating several tasks, particularly the LTS workflows, are present in this
repository. Run the following:

```console
$ composer install
```

to ensure dependencies are present. The tools are in the `bin/maintainers.php` script.
