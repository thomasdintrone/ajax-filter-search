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
rsync -a --exclude-from=.distignore --exclude='/build' ./ "$out/"
( cd build && zip -qr "${slug}-${version}.zip" "${slug}" )

echo "Built build/${slug}-${version}.zip"
unzip -l "build/${slug}-${version}.zip" | tail -n +4 | head -40
