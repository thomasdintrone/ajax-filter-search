#!/usr/bin/env bash
# Browser tests (Playwright + axe + visual + performance budget) against a seeded dev stack.
# Runs inside the official Playwright image so screenshots are identical on every machine and in CI.
#   bin/e2e.sh                       run everything
#   bin/e2e.sh --update-snapshots    accept new visual baselines (review the images before committing)
#   bin/e2e.sh feed.spec.mjs         one spec file
# Needs Docker and `npm ci --ignore-scripts` once (installs the test runner; no browser download).
set -euo pipefail
cd "$(dirname "$0")/.."
PW_VERSION="$(node -p "require('./package.json').devDependencies['@playwright/test']")"
IMAGE="mcr.microsoft.com/playwright:v${PW_VERSION}-noble"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-afse2e}"
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

net="${COMPOSE_PROJECT_NAME}_default"
docker run --rm --network "$net" --ipc=host \
	-e BASE_URL=http://wordpress -e CI=1 \
	-v "$PWD:/work" -w /work/tests/e2e "$IMAGE" \
	npx playwright test -c playwright.config.mjs "$@"
