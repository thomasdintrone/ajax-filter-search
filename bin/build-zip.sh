#!/usr/bin/env bash
# Build a release zip of exactly what ships to WordPress.org (honors .distignore).
# Usage: bin/build-zip.sh      ->  build/ajax-filter-search-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."

slug="ajax-filter-search"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' ajax-filter-search.php | head -1)"
out="build/${slug}"

rm -rf build
mkdir -p "$out"
# Start from tracked files only (git archive), so untracked local material (for example docs-private/)
# can never end up in the package, then drop everything listed in .distignore.
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
git archive HEAD | tar -x -C "$stage"
rsync -a --exclude-from=.distignore "$stage/" "$out/"
( cd build && zip -qr "${slug}-${version}.zip" "${slug}" )

echo "Built build/${slug}-${version}.zip"
unzip -l "build/${slug}-${version}.zip" | tail -n +4 | head -40
