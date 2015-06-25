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

> ## DO NOT PRESS THE GREEN BUTTON
>
> ![Merge button](img/green-button.png)
>
> GitHub provides a button, affectionately called the "green button," for merging pull requests from
> the web interface.
>
> **DO NOT EVER USE IT.**
>
> Because we follow git flow, any changes made on `master` must also be merged to `develop`, and
> this cannot be accomplished from the web interface. Additionally, we request that you annotate
> the merge commits to reference the pull request and any associated issues; this practice
> simplifies determining the source of a feature, and allows developers to discover the rationale
> as well as any discussion that occurred.
>
> The remainder of this document details how to merge pull requests to the repositories.

## Reviewing Pull Requests

We recommend reviewing pull requests directly within GitHub. This allows a public commentary on
changes, providing transparency for all users.

When providing feedback be civil, courteous, and kind. Disagreement is fine, so long as the
discourse is carried out politely. If we see a record of uncivil or abusive comments, we will revoke
your commit privileges and invite you to leave the project.

During your review, consider the following points:

- Does the change have impact? While fixing typos is nice as it adds to the overall quality of the
  project, merging a typo fix at a time can be a waste of effort. (Merging many typo fixes because
  somebody reviewed the entire component, however, *is* useful!) Other examples to be wary of:

  - Changes in variable names. Ask whether or not the change will make understanding the code
    easier, or if it could simply a personal preference on the part of the author.

  - Formatting changes. Most formatting changes should be generated only by a coding standards
    checker/fixer — and those will normally be caught by continuous integration. Ask whether the
    change is generally improving readability/maintenance, or if it could simply be a personal
    preference on the part of the author.

  Essentially: feel free to close issues that do not have impact.

- Do the changes make sense? If you do not understand what the changes are or what they accomplish,
  ask the author for clarification. Ask the author to add comments and/or clarify test case names to
  make the intentions clear.

  At times, such clarification will reveal that the author may not be using the code correctly, or
  is unaware of features that accommodate their needs. If you feel this is the case, work up a code
  sample that would address the issue for them, and feel free to close the issue once they confirm.

- Does the change break backwards compatibility (BC)? If so, work with the contributor to determine
  why the BC break is needed, and whether or not there may be a way to make the change without
  breaking BC. Breaking BC should be done only out of necessity, and any break should have
  accompanying documentation on the impact, as well as how to update applications to accommodate the
  changes.

  If at all possible, try and introduce new behavior and deprecate existing behavior. This allows
  users to gradually migrate over a period of releases. The existing, deprecated behavior can then
  be removed in a later major release.

  Any BC breaks you plan on merging MUST be communicated to the [Zend team](mailto:zf-devteam@zend.com)
  and/or [Community Review team](mailto:zf-crteam@lists.zend.com) to ensure that testing can be done
  on related components and so that the main ZF2 release can be tested.

- Is this a new feature? If so:

  - Does the issue contain narrative indicating the need for the feature? If not, ask them to
    provide that information. Since the issue will be linked in the changelog, this will often be a
    user's first introduction to it.

  - Are new unit tests in place that test all new behaviors introduced? If not, **do not merge** the
    feature until they are!

  - Is documentation in place for the new feature? (See the [documentation
    guidelines](https://github.com/zendframework/documentation/blob/master/CONTRIBUTING.md)). If
    not **do not merge** the feature until it is!

  - Is the feature necessary for general use cases? Try and keep the scope of any given component
    narrow. If a proposed feature does not fit that scope, recommend to the user that they maintain
    the feature on their own, and close the request. You may also recommend that they see if the
    feature gains traction amongst other users, and suggest they re-submit when they can show such
    support.

  - Is the feature BC compatible? If so, there's nothing blocking merging it, so long as it passes
    review, and you can tag it for the next minor release. If it's not, however, you have a decision
    to make: will the next version be a minor, or a major release? If you decide that you are not
    ready for a major release yet, indicate to the author that you are not yet ready to merge, and
    ask them to please keep the patch up-to-date with any merged changes so that it's mergeable when
    you are ready to schedule a new major release.

    This workflow ensures that the author of the patch is responsible for any merge conflicts. Since
    the author is the one most familiar with the changes they are introducing, they are the party
    most likely to resolve conflicts correctly.


## Workflow for merging Pull Requests

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

> #### Configure your checkout
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

> #### Make macros for commit messages
>
> Automate, automate, automate. Create a macro in the editor you have configured git to invoke for
> each of these commit messages; that way you can hit a key combination to fill in the messages for
> you.

### Push the changes upstream

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

### Tag the milestone

Finally, if a feature is merged to `master`, flag it for the next maintenance milestone (e.g.,
"2.0.4"); if merged only to `develop`, flag it for the next minor or major release milestone (e.g.,
"2.1.0"). This allows users to see when a pull request will release and/or was released.

## Tagging

We recommend tagging frequently. Only allow patches to accumulate if they are not providing
substantive changes (e.g., documentation updates, typo fixes, formatting changes). You *may* allow
features to accumulate in the `develop` branch, particularly if you are planning a major release,
but we encourage tagging frequently.

### Maintenance releases

Tag new maintenance releases against the `master` branch.

First, create a branch for the new version:

```console
$ git checkout -b release/2.5.3
```

Next, update the release date for the new version in the `CHANGELOG.md`, and commit the changes.

Merge the release branch into `master` and tag the release. When you do, slurp in the changelog
entry for the new version into the release message. Be aware that header lines (those starting
with `### `) will need to be reformatted to the alternate markdown header format (a line of `-`
characters following the header, at the same width as the header); this ensures that `git` does
not interpret them as comments.

```console
$ git checkout master
$ git merge --no-ff release/2.5.3
$ git tag -s release-2.5.3
```

> #### Tag names
>
> The various component repositories that were created from the original monolithic ZF2 repository
> use the tag name format `release-X.Y.Z`. This is the format that has been in use by the Zend
> Framework project since inception, and we keep it in these repositories for consistency.
>
> New repositories, such as zend-diactoros and zend-stratigility, use simply the semantic version as
> the tag name, without any prefix.
>
> Before you tag, check to see what format the repository uses!

The changelog entry for the above might look like the following:

```markdown
Added
-----

- [#42](https://github.com/organization/project/pull/42) adds documentation!

Deprecated
----------

- Nothing.

Removed
-------

- Nothing.

Fixed
-----

- [#51](https://github.com/organization/project/pull/51) fixes
  Something to be Better.
```

You will then merge to `develop`, as you would for a bugfix:

```console
$ git checkout develop
$ git merge --no-ff release/2.5.3
```

Next, you need to create a CHANGELOG stub for the next maintenance version. Use the
`bin/maintainers.php changelog-bump` command:

```console
$ path/to/maintainers/bin/maintainers.php changelog-bump 2.5.4
```

Spot-check the `CHANGELOG.md` file, and then merge to each of the `master` and `develop` branches:

```console
$ git checkout master
$ git merge --no-ff -m "Bumped version" version/bump
$ git checkout develop
$ git merge --no-ff -m "Bumped master version" version/bump
```

> #### Conflicts
>
> Be aware that this last merge to the `develop` branch will generally result in a conflict, as, if
> you are doing things correctly, you'll have an entry for the next minor or major release in the
> `develop` branch, and you're now merging in a new empty changelog entry for a maintenance release.

Push the two branches and the new tag:

```console
$ git push origin master:master && git push origin develop:develop && git push origin release-2.5.3:release-2.5.3
```

Finally, remove your temporary branches:

```console
$ git branch -d release/2.5.3 version/bump
```

### Feature releases

When you're ready to tag a new minor or major version, you'll follow a similar workflow to tagging a
maintenance release, with a couple of changes.

First, you need to merge the `develop` branch to master:

```console
$ git checkout master
$ git merge develop
```

Assuming you've been following the workflow outlined in this document, this *should* work without
conflicts. If you see conflicts, it's time to read the workflow again!

At this point, you will proceed as you would for a maintenance release. However, before pushing the
branches and tags, do the following:

- Checkout the `develop` branch, and bump the CHANGELOG; use the `--base` argument of the
  `changelog-bump` command to specify the `develop` branch:

  ```console
  $ path/to/maintainers/bin/maintainers.php changelog-bump 2.6.0 --base=develop
  ```

- Merge the `version/bump` branch to `develop`.

At that point, you can push the branches, tag, and remove all temporary branches.

## FAQ

### What if I want to merge a patch made against develop to master?

Occasionally a contributor will issue a patch against the `develop` branch that would be better
suited for the `master` branch; typically these are bugfixes that do not introduce any new features.
When this happens, you need to alter the workflow slightly.

- Checkout a branch against develop; use the pull request number, with the suffix `-dev`.

```console
$ git checkout -b hotfix/1234-dev develop
```

- Merge the patch against that branch.

```console
$ git merge <uri of patch>
```

- Checkout another branch against master. Use the pull request number, with no suffix.

```console
$ git checkout -b hotfix/1234 master
```

- Cherry-pick any commits for the patch in the new branch. You can find the sha1 identifiers for
  each patch in the pull request's "Commits" tab.

```console
$ git cherry-pick <sha1>
```

- Merge the new branch to master and develop just as you would for any bugfix.

```console
$ git checkout master
$ git merge hotfix/1234
$ git checkout develop
$ git merge hotfix/1234
```

- Since you did not merge the first branch, `hotfix/1234-dev` in our example, you'll need to use the
  `-D` switch when removing it from your checkout.

```console
$ git branch -d hotfix/1234
$ git branch -D hotfix/1234-dev
```

### What if I want to merge a patch made against master to develop?

Go for it. One reason for choosing `git-flow` is to simplify merges. Because all changes made
against `master` are backported to `develop`, you can safely merge any change issued against the
`master` branch directly to the `develop` branch without issues.

### What order should CHANGELOG entries be in?

CHANGELOG entries should be in reverse chronological order based on release date, and taking into
account *future* release date.

This means that on the `develop` branch, the top entry should always be the one for the version the
`develop` branch is targeting. Additionally, the `develop` branch should contain a stub for the next
version represented by the `master` branch:

```markdown
## 2.6.0 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.5.3 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
```

Following this practice ensures that as bugfixes are ported to the `develop` branch, merges *should*
occur without issue, or be untangled relatively easily.

If we decide to skip the maintenance release and go directly to a minor or major release, remove the
stub for the maintenance release when merging the `develop` branch back to `master`. This is best
accomplished by adding the `--no-commit` flag when merging, manually removing the stub from the
changelog and staging it, and then finalizing the commit:

```console
$ git merge --no-commit develop
# edit CHANGELOG.md
$ git add CHANGELOG.md
$ git commit
```

In the case that the `master` branch had bugfixes that were never released before a minor/major
release was cut, you'll need to merge the changelog entries for that release into the `develop`
branch's changelog. As an example, consider the following:

```markdown
## 2.6.0 - TBD

### Added

- Useful features that everyone will want.

### Deprecated

- Useless features that are no longer needed.

### Removed

- Nothing.

### Fixed

- Stuff that couldn't be fixed as they require additions.

## 2.5.3 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- A bunch of stuff that was broken
```

The above would be merged to a single changelog entry for 2.6.0 which would look like this:

```markdown
## 2.6.0 - TBD

### Added

- Useful features that everyone will want.

### Deprecated

- Useless features that are no longer needed.

### Removed

- Nothing.

### Fixed

- Stuff that couldn't be fixed as they require additions.
- A bunch of stuff that was broken
```
