#!/usr/bin/env bash
# Browser tests (Playwright + axe + visual + performance budget) against a seeded dev stack.
# Runs inside the official Playwright image so screenshots are identical on every machine and in CI.
#   bin/e2e.sh                       run everything
#   bin/e2e.sh --update-snapshots    accept new visual baselines (review the images before committing)
#   bin/e2e.sh feed.spec.mjs         one spec file
#   AFS_PERF_10K=1 bin/e2e.sh        seed about 10,000 posts and check real HTTP latency (nightly)
#   PW_BROWSER=firefox bin/e2e.sh feed.spec.mjs   another engine (firefox or webkit)
#   AFS_THEME=afs-classic-min bin/e2e.sh   the behaviour and accessibility specs on a classic (non-block) theme
# Needs Docker and `npm ci --ignore-scripts` once (installs the test runner; no browser download).
set -euo pipefail
cd "$(dirname "$0")/.."
PW_VERSION="$(node -p "require('./package.json').devDependencies['@playwright/test']")"
IMAGE="mcr.microsoft.com/playwright:v${PW_VERSION}-noble"
if [ -n "${AFS_THEME:-}" ]; then
	export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-afse2e-classic}" AFS_PORT="${AFS_PORT:-8891}" COMPOSE_FILE="docker-compose.yml:tests/e2e/compose.classic.yml"
	# Screenshots and the speed budget belong to the default theme; every other spec must pass on this one too.
	[ "$#" -gt 0 ] || set -- feed.spec.mjs a11y.spec.mjs contract.spec.mjs
else
	export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-afse2e}"
fi
export AFS_HOST="wordpress"            # WordPress answers to this name inside the compose network
export WP_IMAGE="${WP_IMAGE:-wordpress:php8.4-apache}"
[ -d node_modules/@playwright/test ] || { echo "Run 'npm ci --ignore-scripts' first."; exit 1; }

docker compose up -d --wait
wp() { docker compose run --rm -T wpcli wp "$@"; }
if ! wp core is-installed >/dev/null 2>&1; then
	wp core install --url=http://wordpress --title=AFS --admin_user=admin --admin_password=password --admin_email=dev@example.com --skip-email
	wp plugin activate ajax-filter-search
	wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
fi
[ -z "${AFS_THEME:-}" ] || wp theme activate "$AFS_THEME"
if [ -n "${AFS_PERF_10K:-}" ]; then
	[ "$(wp post list --post_type=post --format=count)" -gt 9000 ] || wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-perf-content.php
	[ "$#" -gt 0 ] || set -- perf-10k.spec.mjs
fi

net="${COMPOSE_PROJECT_NAME}_default"
docker run --rm --network "$net" --ipc=host \
	-e BASE_URL=http://wordpress -e CI=1 -e PW_BROWSER="${PW_BROWSER:-}" -e AFS_PERF_10K="${AFS_PERF_10K:-}" \
	-v "$PWD:/work" -w /work/tests/e2e "$IMAGE" \
	npx playwright test -c playwright.config.mjs "$@"
