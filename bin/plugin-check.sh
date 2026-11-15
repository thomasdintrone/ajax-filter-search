#!/usr/bin/env bash
# Runs the official Plugin Check against the release package (not the working tree), in a throwaway stack.
# Exit 1 on any ERROR; warnings are listed. Needs Docker and internet access on the host.
set -euo pipefail
cd "$(dirname "$0")/.."
export AFS_PORT="${AFS_UPGRADE_PORT:-8896}" COMPOSE_PROJECT_NAME=afspc WP_IMAGE="${WP_IMAGE:-wordpress:php8.4-apache}"
FILES=( -f docker-compose.yml -f docker-compose.upgrade.yml )
wp() { docker compose "${FILES[@]}" run --rm -T wpcli wp "$@" 2>&1 | grep -vE '^ Container ' || true; }
bin/build-zip.sh >/dev/null
tmp="$(mktemp -d)"; unzip -q build/ajax-filter-search-*.zip -d "$tmp"
trap 'docker compose "${FILES[@]}" down -v >/dev/null 2>&1; rm -rf "$tmp"' EXIT
docker compose "${FILES[@]}" up -d --wait >/dev/null 2>&1
wp core install --url="http://localhost:$AFS_PORT" --title=AFS --admin_user=admin --admin_password=password --admin_email=dev@example.com --skip-email >/dev/null
curl -fsSL -o "$tmp/pc.zip" https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip
docker compose "${FILES[@]}" cp "$tmp/pc.zip" wordpress:/var/www/html/pc.zip
docker compose "${FILES[@]}" cp "$tmp/ajax-filter-search" wordpress:/var/www/html/wp-content/plugins/ajax-filter-search
docker compose "${FILES[@]}" exec -T -u root wordpress chown -R www-data:www-data /var/www/html/pc.zip /var/www/html/wp-content/plugins/ajax-filter-search
wp plugin install /var/www/html/pc.zip --activate >/dev/null
wp plugin activate ajax-filter-search >/dev/null
out="$(wp plugin check ajax-filter-search --format=json 2>&1 || true)"
printf '%s\n' "$out" > .cache/plugin-check.json
python3 - <<'PY'
import json, re, sys
raw = open('.cache/plugin-check.json').read()
start = raw.find('[')
rows = []
for line in raw.splitlines():
    line = line.strip()
    if line.startswith('{') or line.startswith('['):
        try:
            data = json.loads(line)
            rows += data if isinstance(data, list) else [data]
        except ValueError:
            pass
errors = [r for r in rows if str(r.get('type', '')).upper() == 'ERROR']
warnings = [r for r in rows if str(r.get('type', '')).upper() == 'WARNING']
for r in errors + warnings:
    print('%-7s %s:%s %s (%s)' % (r.get('type'), r.get('file', ''), r.get('line', ''), str(r.get('message', ''))[:150], r.get('code', '')))
print('plugin-check: %d errors, %d warnings' % (len(errors), len(warnings)))
if not rows and 'Success' not in raw:
    print(raw[:600])
sys.exit(1 if errors else 0)
PY
