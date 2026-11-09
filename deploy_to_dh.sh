#!/bin/bash
# Deploy the current branch to DreamHost: git push, then git pull over ssh.
# (ported from new-DH-user-site's deploy_to_dh.sh, itself based on
# dreams.robnugen.com's deploy_dreams_to_dh.sh)
#
# This only updates a site whose project root on the server is a git checkout
# of this repo. todo.chatforest.com was first deployed with the scp watcher
# (scp_files_to_dh.sh), so its root may not be one yet. Make it one, once, in
# place: git init, add origin, fetch, then check out the branch over the files
# already there. Never rsync the tree over it.
set -e

DEST="todo"                # an ssh Host from ~/.ssh/config
DEST_PATH="todo.chatforest.com"    # project root on the server, relative to ~

# The server pulls over https: the repo is public, so dh_banbanana needs no
# GitHub key. Set this to "origin" (and give the server a deploy key) if the
# repo goes private.
PULL_URL=""

cd "$(dirname "$0")"

if [ "$DEST" = "example" ]; then
    echo "Set DEST and DEST_PATH in $0 first." >&2
    exit 1
fi

if [ -z "$PULL_URL" ]; then
    PULL_URL=$(git remote get-url origin | sed -E 's#^git@github\.com:#https://github.com/#')
fi

BRANCH=$(git rev-parse --abbrev-ref HEAD)

echo "Pushing $BRANCH to origin..."
git push -u origin "$BRANCH"

echo "Pulling $BRANCH on DreamHost..."
# shellcheck disable=SC2029  # expanding locally is the point
ssh "$DEST" "cd $DEST_PATH; git restore .; git pull $PULL_URL $BRANCH"

echo "Deployed $BRANCH to $DEST."
