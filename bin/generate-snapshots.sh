#!/usr/bin/env bash
# (Re)generate tests/snapshots/2.0.0 from the v2.0.0 RELEASE TAG, never from the working tree.
# Review the diff before committing; the snapshots are the executable definition of "2.0.0 behavior".
#   bin/generate-snapshots.sh [tag]        default tag: v2.0.0  (series name = tag without the leading v)
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1
tag="${1:-v2.0.0}"; series="${tag#v}"
git rev-parse -q --verify "refs/tags/$tag" >/dev/null || { echo "Tag $tag not found (git fetch --tags)."; exit 1; }
tree=".cache/afs-$tag"
rm -rf "$tree"; mkdir -p .cache
git worktree prune
git worktree add --detach -f "$tree" "$tag" >/dev/null
trap 'git worktree remove --force "$tree" >/dev/null 2>&1 || true' EXIT
echo "Generating snapshots for series $series from $tag ($(git rev-parse --short "$tag^{commit}"))"
rm -rf "tests/snapshots/$series"
UPDATE_SNAPSHOTS=1 AFS_SNAPSHOT_SERIES="$series" AFS_TEST_PLUGIN_DIR="$PWD/$tree" bin/test.sh --group snapshot
echo "Wrote $(find "tests/snapshots/$series" -type f | wc -l | tr -d ' ') snapshot files to tests/snapshots/$series"
