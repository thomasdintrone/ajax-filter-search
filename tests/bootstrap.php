<?php
/**
 * PHPUnit bootstrap. Run through bin/test.sh (Docker); it supplies WP_TESTS_DIR (the WordPress
 * develop test library for the core version in use) and AFS_TEST_PLUGIN_DIR (the plugin tree under test:
 * the working tree, or a checkout of a release tag when generating snapshots).
 */
$afs_tests_dir  = getenv( 'WP_TESTS_DIR' );
$afs_plugin_dir = getenv( 'AFS_TEST_PLUGIN_DIR' );
if ( ! $afs_tests_dir || ! $afs_plugin_dir || ! file_exists( $afs_plugin_dir . '/ajax-filter-search.php' ) ) {
	fwrite( STDERR, "Run the tests with bin/test.sh (needs WP_TESTS_DIR and AFS_TEST_PLUGIN_DIR).\n" );
	exit( 1 );
}

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
// 1.x theme overrides read this global constant; define it once so output does not depend on test order.
define( 'FILTER_TYPE', '' );

require_once $afs_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $afs_plugin_dir ) {
		require $afs_plugin_dir . '/ajax-filter-search.php';
	}
);

require $afs_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/phpunit/class-afs-test-case.php';
