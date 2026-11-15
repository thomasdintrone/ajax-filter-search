<?php
/**
 * Plugin Name: AFS Spike ESM
 * Description: Spike only. Replaces the shipped script with a hand-written ES module (?afs_variant=esm).
 */

defined( 'ABSPATH' ) || exit;

// Enqueue at render time: on WordPress 6.5 with a block theme, script modules are printed in wp_head
// (after the template has rendered), so a module enqueued later in wp_footer is silently dropped.
add_filter(
	'do_shortcode_tag',
	static function ( $out, $tag ) {
		if ( 'ajax_filter_search' === $tag && isset( $_GET['afs_variant'] ) && 'esm' === $_GET['afs_variant'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_register_script_module( 'afs-spike/esm', plugins_url( 'view.js', __FILE__ ), array(), '1' );
			wp_enqueue_script_module( 'afs-spike/esm' );
			add_action(
				'wp_footer',
				static function () {
					printf(
						'<script>window.afsSettings=%s;</script>',
						wp_json_encode( array( 'restUrl' => esc_url_raw( rest_url( 'afs/v1/results' ) ), 'i18n' => array( 'error' => 'Could not load results.' ) ) )
					);
				},
				1
			);
		}
		return $out;
	},
	10,
	2
);
