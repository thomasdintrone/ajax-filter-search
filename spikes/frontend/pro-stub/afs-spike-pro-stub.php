<?php
/**
 * Plugin Name: AFS Spike Pro Stub
 * Description: Spike only. Stands in for a separate add-on that extends the free plugin's front end. ?afs_pro=first|last (load order) selects the order.
 */

defined( 'ABSPATH' ) || exit;

function afs_spike_pro_enqueue() {
	$variant = isset( $_GET['afs_variant'] ) ? sanitize_key( wp_unslash( $_GET['afs_variant'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$file    = 'interactivity' === $variant ? 'pro-store.js' : ( 'esm' === $variant ? 'pro-esm.js' : '' );
	if ( '' === $file ) {
		return;
	}
	$deps = 'interactivity' === $variant ? array( '@wordpress/interactivity' ) : array();
	wp_register_script_module( 'afs-spike/pro', plugins_url( $file, __FILE__ ), $deps, '1' );
	wp_enqueue_script_module( 'afs-spike/pro' );
}

$afs_spike_when = isset( $_GET['afs_pro'] ) ? sanitize_key( wp_unslash( $_GET['afs_pro'] ) ) : 'off'; // phpcs:ignore WordPress.Security.NonceVerification
if ( 'first' === $afs_spike_when ) {
	add_action( 'wp_enqueue_scripts', 'afs_spike_pro_enqueue', 1 );
} elseif ( 'last' === $afs_spike_when ) {
	add_action( 'wp_footer', 'afs_spike_pro_enqueue', 2 );
}
