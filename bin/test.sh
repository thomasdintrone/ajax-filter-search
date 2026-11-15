#!/usr/bin/env bash
# Run the PHPUnit suite in Docker (no local WordPress, MySQL or wp-env needed).
#
#   bin/test.sh                         # whole suite, working tree, WordPress image below
#   bin/test.sh --group snapshot        # any extra args go to phpunit
#   WP_IMAGE=wordpress:6.8-php8.2-apache bin/test.sh
#   AFS_TEST_PLUGIN_DIR=/abs/path/to/other/checkout bin/test.sh     # test another tree (a release tag)
#   UPDATE_SNAPSHOTS=1 bin/test.sh      # (re)write snapshots; use bin/generate-snapshots.sh instead
#
# The WordPress test library for the core version inside the image is fetched once into .cache/ (svn export).
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1
repo="$PWD"
WP_IMAGE="${WP_IMAGE:-wordpress:7.1-php8.3-apache}"
plugin_dir="${AFS_TEST_PLUGIN_DIR:-$repo}"
[ -f vendor/bin/phpunit ] || { echo "Run 'composer install' first."; exit 1; }
[ -f "$plugin_dir/ajax-filter-search.php" ] || { echo "No plugin at $plugin_dir"; exit 1; }

core_version="$(docker run --rm --entrypoint php "$WP_IMAGE" -r 'require "/usr/src/wordpress/wp-includes/version.php"; echo $wp_version;')"
lib=".cache/wp-tests/$core_version"
if [ ! -d "$lib/includes" ]; then
	echo "Fetching WordPress $core_version test library..."
	mkdir -p "$lib"
	if command -v svn >/dev/null 2>&1 && [ -z "${AFS_NO_SVN:-}" ]; then
		ref="tags/$core_version"
		svn info -q "https://develop.svn.wordpress.org/$ref/tests/phpunit/includes" >/dev/null 2>&1 || ref="trunk"   # pre-releases have no tag
		svn export -q --force "https://develop.svn.wordpress.org/$ref/tests/phpunit/includes" "$lib/includes"
		svn export -q --force "https://develop.svn.wordpress.org/$ref/tests/phpunit/data" "$lib/data"
	else
		# No svn (for example on GitHub runners): sparse git checkout of just the test library at the release tag.
		tmp="$(mktemp -d)"
		# Pre-release versions (for example 7.2-beta5) have no tag in the git mirror: use trunk for those.
		git clone -q --depth 1 --filter=blob:none --sparse --branch "$core_version" https://github.com/WordPress/wordpress-develop.git "$tmp" 2>/dev/null \
			|| { rm -rf "$tmp"; tmp="$(mktemp -d)"; git clone -q --depth 1 --filter=blob:none --sparse --branch trunk https://github.com/WordPress/wordpress-develop.git "$tmp"; }
		git -C "$tmp" sparse-checkout set tests/phpunit/includes tests/phpunit/data
		cp -R "$tmp/tests/phpunit/includes" "$lib/includes"
		cp -R "$tmp/tests/phpunit/data" "$lib/data"
		rm -rf "$tmp"
	fi
fi

net="afs-test-net-$$"; db="afs-test-db"
cleanup() { docker rm -f "$db" >/dev/null 2>&1 || true; docker network rm "$net" >/dev/null 2>&1 || true; }
trap cleanup EXIT
docker rm -f "$db" >/dev/null 2>&1 || true
docker network create "$net" >/dev/null
docker run -d --name "$db" --network "$net" -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wptest mariadb:10.5 >/dev/null
# The image starts a temporary server for first-run init, then restarts: wait for the second "ready" line.
for _ in $(seq 1 90); do
	[ "$(docker logs "$db" 2>&1 | grep -c 'ready for connections')" -ge 2 ] && docker exec "$db" mariadb -uroot -proot -e 'select 1' wptest >/dev/null 2>&1 && break
	sleep 2
done

echo "Testing $plugin_dir on WordPress $core_version ($WP_IMAGE), series ${AFS_SNAPSHOT_SERIES:-2.0.0}"
docker run --rm --network "$net" \
	-v "$repo":/repo -v "$plugin_dir":/plugin:ro -v "$repo/$lib":/wp-tests:ro \
	-e WP_TESTS_DIR=/wp-tests -e AFS_TEST_PLUGIN_DIR=/plugin -e WP_TESTS_DB_HOST="$db" \
	-e AFS_MULTISITE="${AFS_MULTISITE:-}" -e UPDATE_SNAPSHOTS="${UPDATE_SNAPSHOTS:-}" -e AFS_SNAPSHOT_SERIES="${AFS_SNAPSHOT_SERIES:-2.0.0}" \
	-w /repo --entrypoint php "$WP_IMAGE" -d memory_limit=512M ${AFS_PHP_FLAGS:-} vendor/bin/phpunit -c phpunit.xml.dist "$@"
