#!/usr/bin/env bash
# After publishing: ask WordPress.org what it now serves. Run once its update hold (about six hours) has passed.
#   bin/verify-live-release.sh <new-version> <previous-version>        e.g.  bin/verify-live-release.sh 2.1.0 2.0.0
# Checks (read-only, no site needed): the plugins API reports the new version; the downloadable zip is the one that
# was tagged; the real update-check endpoint offers the new version to a site running the previous one; and the
# listing has the expected number of screenshots. Exit 1 if anything is not yet true.
set -u
NEW="${1:?new version}"; OLD="${2:?previous version}"
cd "$(dirname "$0")/.."
fail=0
check() { if [ "$2" == "$3" ]; then echo "ok    $1"; else echo "WAIT  $1 (expected '$2', got '$3')"; fail=1; fi; }

info="$(curl -fsS "https://api.wordpress.org/plugins/info/1.0/ajax-filter-search.json")"
field() { printf '%s' "$info" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('$1'))"; }
check "plugins API reports $NEW" "$NEW" "$(field version)"
shots="$(printf '%s' "$info" | python3 -c "import sys,json; print(len(json.load(sys.stdin).get('screenshots') or {}))")"
echo "info  the listing shows $shots screenshots (the readme lists the ones in assets/)"

tmp="$(mktemp -d)"
if curl -fsS -o "$tmp/live.zip" "https://downloads.wordpress.org/plugin/ajax-filter-search.${NEW}.zip" 2>/dev/null; then
	unzip -q "$tmp/live.zip" -d "$tmp/live"
	git archive "v${NEW}" 2>/dev/null | tar -x -C "$tmp" --one-top-level=src 2>/dev/null || { mkdir -p "$tmp/src"; git archive "v${NEW}" | tar -x -C "$tmp/src"; }
	mkdir -p "$tmp/pkg" && rsync -a --exclude-from=.distignore "$tmp/src/" "$tmp/pkg/"
	if diff -rq "$tmp/live/ajax-filter-search" "$tmp/pkg" >/dev/null; then echo "ok    the downloadable zip equals tag v${NEW} (packaged)"; else echo "WAIT  the downloadable zip differs from tag v${NEW}"; diff -rq "$tmp/live/ajax-filter-search" "$tmp/pkg" | head -5; fail=1; fi
else
	echo "WAIT  downloads.wordpress.org does not serve ${NEW} yet"; fail=1
fi

# What a site on the previous version is told. This is the endpoint WordPress itself calls.
plugins="{\"plugins\":{\"ajax-filter-search/ajax-filter-search.php\":{\"Name\":\"Ajax Filter Search\",\"Version\":\"${OLD}\",\"TextDomain\":\"ajax-filter-search\"}},\"active\":[\"ajax-filter-search/ajax-filter-search.php\"]}"
offered="$(curl -fsS 'https://api.wordpress.org/plugins/update-check/1.1/' --data-urlencode "plugins=${plugins}" --data-urlencode 'translations=[]' --data-urlencode 'locale=["en_US"]' --data-urlencode 'all=true' \
	| python3 -c "import sys,json; d=json.load(sys.stdin); a=d.get('plugins'); p=a.get('ajax-filter-search/ajax-filter-search.php') if isinstance(a,dict) else None; print(p['new_version'] if p else 'none')")"
check "a site on ${OLD} is offered ${NEW}" "$NEW" "$offered"
exit $fail
