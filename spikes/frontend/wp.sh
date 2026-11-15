#!/usr/bin/env bash
# Usage: spikes/frontend/wp.sh <65|71> <wp-cli args...>   Runs WP-CLI inside a spike stack.
set -euo pipefail
case "$1" in 65) P=afs65; PORT=8865; IMG=wordpress:6.5-php8.2-apache;; 71) P=afs71; PORT=8871; IMG=wordpress:php8.4-apache;; *) echo "stack must be 65 or 71" >&2; exit 2;; esac
shift
cd "$(dirname "$0")/../.."
AFS_PORT="$PORT" WP_IMAGE="$IMG" docker compose -p "$P" -f docker-compose.yml -f spikes/frontend/compose.spikes.yml run --rm -T wpcli wp "$@" 2>&1 | grep -vE "^ Container "
