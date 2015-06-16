# LTS Release Workflow

As of the time of writing, version 2.4 is our Long-Term Support release. This causes a few
headaches, as that version pre-dates the component split.

The recommendation is to patch the individual component(s) first, then backport the fix to an LTS
tag for the component, and finally to backport the patch to the ZF2 release itself.

## 1. Create the initial component(s) patch

Once you have a fix in place for the component, you can create a patchfile for the change using `git
format-patch`. This is best done using the branch on which the change was applied.

```console
$ git checkout -b hotfix/foo master
$ git merge <URI for pull request>
$ git format-patch master...HEAD
```

The above will create a file such as `0001-Use-the-Host-port-when-port-forwarding-is-detected.patch`
in the component root.

> Dealing with multiple commits
>
> If the patch was made over multiple commits, do an interactive rebase to squash them before
> creating the patch:
> 
> ```console
> $ git rebase -i master
> # squash them into a single commit
> $ git format-patch master...HEAD
> ```

## 2. Apply the patch to an LTS release

Checkout a branch based on the last LTS release:

```console
$ git checkout -b release-2.4 release-2.4.2
```

Next, apply the patch:

```console
$ git am < {patchfile}
```

Create a CHANGELOG entry for the change, and commit it. (See the [MAINTAINERS.md](MAINTAINERS.md)
file for information on CHANGELOGS).

Finally, tag the release:

```console
$ git tag -s release-2.4.3
```

When you tag, make sure the tag message includes the CHANGELOG for the release.

## 3. Increment the LTS version of all other components

Because the LTS version is for the framework as a whole, *all* components must be released
simultaneously with the same new LTS version. The script `bin/maintainer.php` will help you in
tagging those:

```console
$ bin/maintainer.php lts-release 2.4 \
> --exclude=zend-http,zend-mvc \
> --basePath=path/above/component/checkouts
```

Note that you should **exclude** any components that were involved in the patch; those should
already be tagged.

Verify that the process finished successfully; if it did not, find out what went wrong.

To push all LTS repos, use the `lts-components` target of the `bin/maintainers.php` command:

```console
$ cd path/above/component/checkouts
$ for COMPONENT in $(path/to/maintainers/bin/maintainer.php lts-components);do
> (cd ${COMPONENT} ; git push origin release-2.4.3:release-2.4.3)
> done
```

Obviously, substitute the correct release tag!

## 4. Backport the patch to the ZF2 repository

TBD
