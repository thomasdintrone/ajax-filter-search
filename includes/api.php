<?php
/**
 * Public extension API: a version number and registries that add-ons can fill.
 *
 * The free plugin only stores registrations; it never checks licenses or contacts anything. An add-on should
 * compare `AFS_API_VERSION` with the version it needs and show an admin notice (not a fatal error) if the free
 * plugin is older.
 *
 * Register on the `afs_register` action (fires on `init`, priority 5), for example:
 *
 *     add_action( 'afs_register', function () {
 *         afs_register_field( 'rating', array( 'label' => 'Rating' ) );
 *     } );
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

/** Integer, bumped only when a registry or hook changes in a way an add-on must react to. */
define( 'AFS_API_VERSION', 1 );

function afs_register_field( $id, $args = array() ) {
	return AFS_Registry::register( 'fields', $id, $args );
}

function afs_register_design( $id, $args = array() ) {
	return AFS_Registry::register( 'designs', $id, $args );
}

function afs_register_template_part( $id, $args = array() ) {
	return AFS_Registry::register( 'template_parts', $id, $args );
}

function afs_register_admin_tab( $id, $args = array() ) {
	return AFS_Registry::register( 'admin_tabs', $id, $args );
}

function afs_register_rest_meta( $id, $args = array() ) {
	return AFS_Registry::register( 'rest_meta', $id, $args );
}

/** @return array<string,array> */
function afs_registered( $type ) {
	return AFS_Registry::all( $type );
}

/**
 * URL of the RSS feed for a filter state.
 *
 * @param array $config Normalized config (AFS_Renderer::normalize_config()).
 * @param array $state  Normalized state (AFS_Renderer::normalize_state()).
 * @return string
 */
function afs_feed_url( $config, $state ) {
	return AFS_Feed::url( $config, $state );
}
