#!/usr/bin/env bash
# Usage: spikes/frontend/setup-stack.sh <compose-project> <port> <wp-image>
# Installs WordPress in a running spike stack, activates the plugin and seeds content.
set -euo pipefail
P="$1"; PORT="$2"; IMG="$3"
cd "$(dirname "$0")/../.."
wp() { AFS_PORT="$PORT" WP_IMAGE="$IMG" docker compose -p "$P" -f docker-compose.yml -f spikes/frontend/compose.spikes.yml run --rm -T wpcli wp "$@"; }
wp core install --url="http://localhost:$PORT" --title=AFS --admin_user=admin --admin_password=password --admin_email=dev@example.com --skip-email
wp plugin activate ajax-filter-search
wp eval-file wp-content/plugins/ajax-filter-search/bin/seed-test-content.php
wp core version
