# Maintainers Guide

This guide is intended for *maintainers* — anybody with commit access to one or more Zend Framework
repositories.

We use the [git flow methodology](http://nvie.com/posts/a-successful-git-branching-model/) for
managing the various Zend Framework repositories. At a glance, this means:

- a **master** branch. This branch MUST be releasable at all times. Commits and merges against
  this branch MUST contain only bugfixes and/or security fixes. Maintenance releases are tagged
  against master.
- a **develop** branch. This branch contains *new features*, and will either become the next minor
  (feature) release or next major release. Typically, major releases are reserved for backwards
  *incompatible* changes, but can also be used to signal major new features.

Maintainers can choose to release new maintenance releases with each new patch, or accumulate
patches until a significant number of changes are in place. Security fixes, however, must be
released immediately and coordinated with the Zend Framework team to ensure an advisory is made, a
CVE is created, and that the fix is backported to the Long Term Support release.

## Workflow for merging Pull Requests (PR)

Check which branch the PR was made against — patches made against the `develop` branch should only
be merged to `develop`. Patches made against the `master` branch may be merged to only `develop`, or
both `develop` AND `master`. The criteria to use in this latter situation is: does the patch
introduce anything new to the API? does it potentially mark a change in behavior of any sort? If the
answer to either of these is "yes", merge only to the `develop` branch.

To sum up:

- If the commit is a bugfix, merge to **both** `master` **and** `develop`.
- If the commit is a new feature, merge to `develop` **only**.

### Start the process with a clean checkout

Before you begin merging, do the following:

```console
$ git fetch origin
$ git checkout master && git rebase origin/master
$ git checkout develop && git rebase origin/develop
```

This will set each branch to the latest commit from the canonical repository.

### Create a local merge branch

Next, create a new branch locally for the change, based off the appropriate branch. Use the prefix
`hotfix/` for fixes, and `feature/` for features; typically, use the PR number as the branch name.

```console
$ git checkout -b hotfix/2854 master
$ git checkout -b feature/2719 develop
```

### Pull in the changeset

Now you're staged and ready to pull the changeset in. On the bar that occurs before comments, on the
left side is a little "information" icon. Click on this, and you get a popup dialog showing the
steps to take to manually merge. If you click the icon next to the second step, it will copy a "git
pull" command to your clipboard. Paste this into your terminal.

> #### Hub
>
> GitHub develops a [hub](https://github.com/github/hub) command that provides a superset of
> features for your git executable. It can make the following far simpler:
>
> ```console
> $ git merge <url to PR>
> ```
>
> We recommend using the hub command to make your life as a maintainer easier!

At this point, you can do a final review of the patch — run tests, run CS checks, normalize code,
etc. Commit any changes you need to make *in* *that* *branch*.

### Create a Changelog

Starting with v2.5 of all components, and v1 of new components such as Diactoros and Stratigility,
we follow [Keep a CHANGELOG](http://keepachangelog.com/). The format is simple:

```markdown
# CHANGELOG

## X.Y.Z - YYYY-MM-DD

### Added

- [#42](https://github.com/organization/project/pull/42) adds documentation!

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#51](https://github.com/organization/project/pull/51) fixes
  Something to be Better.
```

Each version gets a changelog entry in the project's `CHANGELOG.md` file. Not all changes need to be
noted; things like coding standards fixes, continuous integration changes, or typo fixes do not need
to be communicated. However, anything that falls under an addition, deprecation, removal, or fix
MUST be noted. Please provide a succinct but *narrative* description for the change you merge. Once
written, commit the `CHANGELOG.md` file against your local branch.

### Merge to the canonical branches

At this point, you can finally merge to the canonical branches!

Checkout the target branch (`master` or `develop`), and merge the change in using the `--no-ff`
flag. For changes going to master, you'll need to do both master and develop.

> ### Configure your checkout
>
> Another way you can simplify your job is to configure your checkout. You can omit the need to type
> the `--no-ff` flag on each merge by adding the following configuration line to the branch
> configuration in the checkout's `.git/config` file:
>
> ```dosini
> [branch "master"]
>     ...
>     mergeoptions = --no-ff
> ```

As an example of merging a hotfix:

```console
$ git checkout master
$ git merge --no-ff hotfix/2854
$ git checkout develop
$ git merge --no-ff hotfix/2854
```

Note that, because this is a hotfix, it was merged to both `master` *and* `develop`!

As an example of merging only a feature:

```
$ git checkout develop
$ git merge --no-ff feature/2719
```

Since this is a feature, it was merged to `develop` *only*.

The commit message for the target branch should include the merge statement, as well as a line like
the following:

```
Close #2854
```

If merging to both `master` and `develop`, the `develop` branch should indicate that it's *forward
porting* a fix:

```
Forward port #2854
```

If the fix will close other PRs or issues, also close those in the target branch using the
`Fix #...` or `Close #...` notation.

> ### Make macros for commit messages
>
> Automate, automate, automate. Create a macro in the editor you have configured git to invoke for
> each of these commit messages; that way you can hit a key combination to fill in the messages for
> you.


At this point, you can push your changes. If you committed a bugfix, push both branches:

```console
$ git push origin master:master && git push origin develop:develop
```

If committing a feature, push only the `develop` branch:

```console
$ git push origin develop:develop
```

Once pushed, you can delete the merge branch you created.

> ### Conflicts
>
> Occasionally, more than one maintainer will be merging to the same repository. As such, do a quick
> `git fetch origin` prior to pushing to check for changes. If you see any, you'll need to reset
> your branches, and re-merge:
>
> ```console
> $ git checkout develop && git reset --hard origin/develop
> $ git checkout master && git reset --hard origin/master
> $ git merge --no-ff hotfix/2719
> $ git checkout develop
> $ git merge --no-ff hotfix/2719
> ```
>
> If you notice merge conflicts, delete your merge branch, and start again from the top.

Finally, if a feature is merged to `master`, flag it for the next maintenance milestone (e.g.,
"2.0.4"); if merged only to `develop`, flag it for the next minor or major release milestone (e.g.,
"2.1.0"). This allows users to see when a pull request will release and/or was released.
