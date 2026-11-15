<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package ajax-filter-search
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'afs_settings' );
delete_option( 'afs_cache_version' );
delete_metadata( 'user', 0, 'afs_migration_notice_dismissed', '', true );

if ( is_multisite() ) {
	delete_site_option( 'afs_settings' );
	delete_site_option( 'afs_cache_version' );
}
