#!/usr/bin/env bash
# Dev only: HTTP smoke test against the wp-env site seeded by bin/seed-test-content.php.
# Usage: bin/smoke-test.sh [base-url]   (default http://localhost:8890)
B="${1:-http://localhost:8890}"
fail=0
check() { # name, expected, actual
	if [ "$2" == "$3" ]; then echo "ok    $1"; else echo "FAIL  $1 (expected '$2', got '$3')"; fail=1; fi
}
articles() { curl -s "$1" | grep -o '<article class="[^"]*afs-item' | wc -l | tr -d ' '; }

check "single page renders 10 items"        10 "$(articles "$B/afs-single/")"
check "legacy filter_by limits tabs (All + 2)"  3 "$(curl -s "$B/afs-legacy/" | grep -o 'data-afs-term=' | wc -l | tr -d ' ')"
check "block page renders 4 items"           4 "$(articles "$B/afs-block/")"
check "two feeds on one page"               8 "$(articles "$B/afs-double/")"
check "no-JS search deep link"               5 "$(articles "$B/afs-single/?afs_s=Quarterly")"
check "drafts/private never listed"          0 "$(articles "$B/afs-single/?afs_s=SECRET")"
check "assets absent on other pages"         0 "$(curl -s "$B/sample-page/" | grep -c 'afs.js')"

rest() { curl -s -o /dev/null -w '%{http_code}' "$B/wp-json/afs/v1/results?$1"; }
check "REST ok"                              200 "$(rest 'post_type=post&post_tax=category')"
check "REST rejects per_page=9999"           400 "$(rest 'posts_per_page=9999')"
check "REST rejects month=13"                400 "$(rest 'afs_month=13')"
check "REST rejects page=0"                  400 "$(rest 'afs_page=0')"
check "REST unknown post type falls back"    "$(curl -s "$B/wp-json/afs/v1/results?post_type=post" | python3 -c 'import sys,json;print(json.load(sys.stdin)["found"])')" \
                                             "$(curl -s "$B/wp-json/afs/v1/results?post_type=revision" | python3 -c 'import sys,json;print(json.load(sys.stdin)["found"])')"
check "REST off-site base_url ignored"       0 "$(curl -s "$B/wp-json/afs/v1/results?base_url=http://evil.example/x" | grep -c 'evil.example')"
check "REST escapes search text"             0 "$(curl -s "$B/wp-json/afs/v1/results?afs_s=%3Cscript%3E" | grep -c '<script>')"

exit $fail
