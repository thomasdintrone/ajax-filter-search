#!/usr/bin/env bash
# Verify a release zip contains only approved production files, and that `git archive` honors export-ignore.
# Usage: bin/check-package.sh [build/ajax-filter-search-<version>.zip]   (exit 1 on any violation)
set -u
cd "$(dirname "$0")/.." || exit 1
zip="${1:-$(ls build/ajax-filter-search-*.zip 2>/dev/null | head -1)}"
[ -f "$zip" ] || { echo "check-package: no zip found (run bin/build-zip.sh first)"; exit 1; }
fail=0; bad() { echo "FAIL  $1"; fail=1; }

# 1. Every entry sits under ajax-filter-search/ and its top level is on the allowlist.
allowed=" ajax-filter-search.php uninstall.php readme.txt LICENSE admin afs-template blocks includes languages public "
entries="$(unzip -Z1 "$zip")"
echo "$entries" | grep -qv '^ajax-filter-search/' && bad "entries outside the ajax-filter-search/ folder"
for top in $(echo "$entries" | sed -n 's#^ajax-filter-search/\([^/]*\).*#\1#p' | sort -u); do
  case "$allowed" in *" $top "*) ;; *) bad "unexpected top-level entry in zip: $top";; esac
done

# 2. Forbidden material, wherever it appears in the zip.
forbidden='(^|/)(\.ai|spikes|\.git[a-z]*|docs|docs-private|design|bin|tests|\.github|\.wordpress-org|node_modules|vendor|build|_notes|\.sass-cache)(/|$)|\.(md|patch|zip|log|map|scss|scssc|sh)$|(^|/)(composer\.(json|lock)|phpunit\.xml\.dist|docker-compose\.yml|phpcs\.xml\.dist|\.wp-env[a-z.]*|\.distignore|config\.codekit)$'
echo "$entries" | grep -E "$forbidden" | sed 's/^/FAIL  forbidden file in zip: /' | { grep . && fail=1; }

# 3. A zip must contain the essentials.
for need in ajax-filter-search.php readme.txt includes/class-afs-rest.php; do echo "$entries" | grep -qx "ajax-filter-search/$need" || bad "missing required file: $need"; done

# 4. git archive must not export repo-only folders (export-ignore).
if [ -d .git ]; then
  leak="$(git archive HEAD | tar -t | grep -E '^(\.ai|docs|design|bin|\.wordpress-org|docs-private)(/|$)' | head -3)"
  [ -n "$leak" ] && bad "git archive exports repo-only paths: $(echo "$leak" | tr '\n' ' ')"
fi

[ $fail -eq 0 ] && echo "check-package: OK ($(echo "$entries" | grep -vc '/$') files, top level: $(echo "$entries" | sed -n 's#^ajax-filter-search/\([^/]*\).*#\1#p' | sort -u | tr '\n' ' '))"
exit $fail
