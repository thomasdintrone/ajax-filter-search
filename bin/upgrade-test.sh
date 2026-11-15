#!/usr/bin/env bash
# Upgrade test: install a published release, seed content and settings, replace the plugin with the build from this
# working tree (as a WordPress update would), and check nothing broke.
#   bin/upgrade-test.sh 1.0.4      a site coming from the last 1.x release
#   bin/upgrade-test.sh 2.0.0      a site on the current release; output must be identical after the upgrade
# Needs Docker and internet access (downloads the release from wordpress.org). Uses port 8895 (AFS_UPGRADE_PORT).
set -euo pipefail
cd "$(dirname "$0")/.."
BASE="${1:?usage: bin/upgrade-test.sh <published-version>}"
PORT="${AFS_UPGRADE_PORT:-8895}"
export AFS_PORT="$PORT" COMPOSE_PROJECT_NAME="afsup${BASE//./}" WP_IMAGE="${WP_IMAGE:-wordpress:php8.4-apache}"
FILES=( -f docker-compose.yml -f docker-compose.upgrade.yml )
B="http://localhost:$PORT"
fail=0
check() { if [ "$2" == "$3" ]; then echo "ok    $1"; else echo "FAIL  $1 (expected '$2', got '$3')"; fail=1; fi; }
wp() { docker compose "${FILES[@]}" run --rm -T wpcli wp "$@" 2>&1 | grep -vE '^ Container ' || true; }
items() { grep -o '<article class="[^"]*afs-item' | wc -l | tr -d ' '; }
# Output made comparable between two runs: the per-request summary ids and whitespace between tags.
norm() { sed -E 's/afs-summary-[0-9]+/afs-summary-N/g; s/Post excerpt [0-9]+/Post excerpt N/g' | tr -s '\n\t ' ' ' | sed 's/> </></g'; }

bin/build-zip.sh >/dev/null
zip="$(ls build/ajax-filter-search-*.zip | head -1)"
tmp="$(mktemp -d)"; unzip -q "$zip" -d "$tmp"
trap 'docker compose "${FILES[@]}" down -v >/dev/null 2>&1; rm -rf "$tmp"' EXIT

docker compose "${FILES[@]}" up -d --wait
wp core install --url="$B" --title=AFS --admin_user=admin --admin_password=password --admin_email=dev@example.com --skip-email >/dev/null
# Downloaded on the host and copied in: some networks break TLS inside containers.
curl -fsSL -o "$tmp/base.zip" "https://downloads.wordpress.org/plugin/ajax-filter-search.${BASE}.zip"
docker compose "${FILES[@]}" cp "$tmp/base.zip" wordpress:/var/www/html/afs-base.zip
docker compose "${FILES[@]}" exec -T -u root wordpress chown www-data:www-data /var/www/html/afs-base.zip
wp plugin install /var/www/html/afs-base.zip --activate | tail -1
check "release $BASE is installed and active" active "$(wp plugin get ajax-filter-search --field=status | tr -d '\r')"
wp eval-file - < bin/seed-test-content.php | tail -1
SETTINGS='{"afs_general_post_type":"post","afs_general_post_taxonomy":"category","afs_general_posts_per_page":"7","afs_general_show_filters":"1","afs_general_views":"1"}'
wp option update afs_settings "$SETTINGS" --format=json >/dev/null
before_settings="$(wp option get afs_settings --format=json)"

pages=( afs-single afs-double afs-legacy )
for p in "${pages[@]}"; do curl -s "$B/$p/" | norm > "$tmp/before-$p.txt"; done
rest_before="$(curl -s "$B/wp-json/afs/v1/results?afs_term=news&posts_per_page=5" | norm)"
echo "-- on $BASE: single page lists $(curl -s "$B/afs-single/" | items) items"

# Anything the OLD release logged is its own business; only entries after the swap count.
wp eval 'file_put_contents( WP_CONTENT_DIR . "/debug.log", "" );' >/dev/null
echo "-- replacing the plugin with the build from this working tree"
docker compose "${FILES[@]}" exec -T -u root wordpress sh -c 'rm -rf /var/www/html/wp-content/plugins/ajax-filter-search'
docker compose "${FILES[@]}" cp "$tmp/ajax-filter-search" wordpress:/var/www/html/wp-content/plugins/ajax-filter-search
docker compose "${FILES[@]}" exec -T -u root wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/ajax-filter-search
check "new files are in place"                       yes "$(docker compose "${FILES[@]}" exec -T wordpress sh -c 'test -f /var/www/html/wp-content/plugins/ajax-filter-search/includes/class-afs-query.php && echo yes || echo no' | tr -d '\r')"
check "plugin still active"                          active "$(wp plugin get ajax-filter-search --field=status | tr -d '\r')"
check "settings unchanged"                           "$before_settings" "$(wp option get afs_settings --format=json)"

echo "-- after the upgrade"
for p in "${pages[@]}"; do
	check "$p returns 200"                           200 "$(curl -s -o /dev/null -w '%{http_code}' "$B/$p/")"
done
check "single page honours the saved page size (7)"  7 "$(curl -s "$B/afs-single/" | items)"
check "REST route answers"                           200 "$(curl -s -o /dev/null -w '%{http_code}' "$B/wp-json/afs/v1/results?posts_per_page=5")"
check "REST only lists published content"            0 "$(curl -s "$B/wp-json/afs/v1/results?afs_s=SECRET" | grep -c 'SECRET')"
check "1.x ajax action still answers"                200 "$(curl -s -o /dev/null -w '%{http_code}' -d 'action=my_action&filingType=news' "$B/wp-admin/admin-ajax.php")"
check "settings page callback loads without error"   ok "$(wp eval 'require_once ABSPATH . "wp-admin/includes/plugin.php"; echo class_exists("AFSAdmin") ? "ok" : "missing";' | tail -1 | tr -d '\r')"
log="$(wp eval 'echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? file_get_contents( WP_CONTENT_DIR . "/debug.log" ) : "";')"
fatals="$(printf '%s\n' "$log" | grep -ciE 'PHP (Fatal|Parse)|PHP (Warning|Notice|Deprecated).*ajax-filter-search' || true)"
[ "$fatals" == "0" ] || printf '%s\n' "$log" | grep -iE 'PHP (Fatal|Parse|Warning|Notice|Deprecated)' | head -5
check "no PHP fatals, or warnings from the plugin, in the log" 0 "$fatals"

if [ "$BASE" == "2.0.0" ]; then
	echo "-- 2.0.0 to this build: output must be identical"
	for p in "${pages[@]}"; do check "$p output identical" "$(cat "$tmp/before-$p.txt")" "$(curl -s "$B/$p/" | norm)"; done
	# afs-single page size changed? settings were identical, so the markup must be too
	check "REST output identical" "$rest_before" "$(curl -s "$B/wp-json/afs/v1/results?afs_term=news&posts_per_page=5" | norm)"
fi
exit $fail
