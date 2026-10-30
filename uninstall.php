<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package ajax-filter-search
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'afs_settings' );

if ( is_multisite() ) {
	delete_site_option( 'afs_settings' );
}
