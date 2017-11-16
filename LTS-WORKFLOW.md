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

> ### Signed tags are REQUIRED
>
> Always use the `-s` flag when tagging, and make sure you have setup PGP or GPG
> to allow you to create signed tags. Unsigned tags _**will be revoked**_ if
> discovered.

## 3. Increment the LTS version of all other components

Because the LTS version is for the framework as a whole, *all* components must be released
simultaneously with the same new LTS version. The script `bin/maintainer.php` will help you in
tagging those:

```console
$ bin/zf-maintainer lts:release 2.4 \
> --exclude=zend-http --exclude=zend-mvc \
> --basePath=path/above/component/checkouts
```

Note that you should **exclude** any components that were involved in the patch; those should
already be tagged.

Verify that the process finished successfully; if it did not, find out what went wrong. When it
completes successfully, it will print out the new release version tagged.

To push all LTS repos, use the `lts:components` target of the `bin/zf-maintainer` command:

```console
$ cd path/above/component/checkouts
$ for COMPONENT in $(path/to/maintainers/bin/zf-maintainer lts:components | grep '^zend-');do
> (cd ${COMPONENT} ; git push origin release-2.4.3:release-2.4.3)
> done
```

Obviously, substitute the correct release tag!

## 4. Backport the patch to the ZF2 repository

To backport a patch, use the `lts:patch` target of the `bin/zf-maintainer` command:

```console
$ cd path/to/zf2/checkout
$ path/to/maintainers/bin/zf-maintainer lts:patch \
> --component=zend-view \
> --patchfile=path/to/zend-view/checkout/name-of-patchfile.patch \
> --target=./name-of-patchfile.patch
```

This will rewrite the patch so it can be applied against the LTS branch of ZF2.

Repeat the above for each component that has patches you need to release.

Next, you need to create a temporary release branch and apply the patch.

```console
$ path/to/maintainers/bin/mainter.php lts-stage 2.4 \
> --patchfile=./name-of-patchfile.patch
```

This script will apply the patchfile, and then create a commit that:

- bumps the `Zend\Version\Version::VERSION` constant.
- updates the `README.md` file to reference the new version.
- updates the `CHANGELOG.md` file to provide patch details.

> ### Specifying multiple patchfiles
>
> If you created multiple patch files and want to apply them all, you can specify a comma-delimited
> list of filenames to the `--patchfile` argument:
>
> ```console
> $ path/to/maintainers/bin/mainter.php lts-stage 2.4 \
> > --patchfile=./name-of-patchfile.patch,./another-patchfile.patch,./etc.patch
> ```

Check for errors applying the patch(es) (there should not be any), and run the tests specific to the
patch(es):

```console
$ cd tests
$ ../vendor/bin/phpunit ZendTest/View/Helper/ServerUrl.php
```

> ### Watch out for stale dependencies!
>
> One common issue when running the tests is if you have done a composer install or update from the
> a version >= 2.5.0; in such a case, the individual components have been installed in the vendor
> directory, and now take precedence over the library code!
>
> If you observe failing tests when there shouldn't be, run `composer update` and then re-run the
> tests.

At this point, you can now tag; the `lts-stage` will provide you with the `git tag` command to
use. You can then push the tag and delete the branch:

```console
$ git push origin <tagname>:<tagname>
$ git checkout master
$ git branch -D release-2.4
```

## 5. Notify the Zend team

Now that the tag is made, the Zend team will need to build and release distribution packages. As
such, please coordinate with them whenever you tag, so that they can do so as soon as possible after
a tag is created.

If you cannot find one of them in the usual IRC channels, please send an email to
zf-deveam@zend.com.
