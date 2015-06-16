#!/bin/bash
if [ "$#" -ne 1 ];then
    echo "Please provide the minor version for which you want to create a patch release:"
    echo "    zend-version.sh <minor>"
    exit 1
fi

MINOR=$1
LATEST=$(git tag | grep "release-${MINOR}" | sort -V | tail -n 1 | grep -Po "[1-9][0-9]*\.[0-9]+\.[0-9]+")

if [[ "" == "${LATEST}" ]];then
    VERSION=${MINOR}.0
    git checkout -b release-${MINOR} develop
else
    VERSION=$(echo ${LATEST} | sed -r 's/([1-9][0-9]*\.)([0-9]+\.)([0-9]+)/echo "\1\2$((\3+1))"/e')
    git checkout -b release-${MINOR} release-${LATEST}
fi

sed --in-place -r "s/(\s+const VERSION = ')[^']+(';)/\1${VERSION}\2/g" src/Version.php
git commit -a -m "Bump to version ${VERSION}"
git tag -s -m "zend-version ${VERSION}" release-${VERSION}
git checkout master
git branch -D release-${MINOR}

echo "Version bump complete; please verify the tag, and then push:"
echo "    git push origin release-${VERSION}"
