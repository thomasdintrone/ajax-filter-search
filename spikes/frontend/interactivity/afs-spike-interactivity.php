<?php
/**
 * Plugin Name: AFS Spike Interactivity
 * Description: Spike only. Re-skins the AFS widget markup with Interactivity API directives (?afs_variant=interactivity) or router directives (?afs_variant=router).
 */

defined( 'ABSPATH' ) || exit;

function afs_spike_variant() {
	return isset( $_GET['afs_variant'] ) ? sanitize_key( wp_unslash( $_GET['afs_variant'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
}

// The shipped script would double-bind; every non-baseline variant removes it.
add_action(
	'wp_footer',
	static function () {
		if ( '' !== afs_spike_variant() && 'baseline' !== afs_spike_variant() ) {
			wp_dequeue_script( 'afs-script' );
		}
	},
	1
);

add_filter(
	'do_shortcode_tag',
	static function ( $out, $tag ) {
		$variant = afs_spike_variant();
		if ( 'ajax_filter_search' !== $tag || ! in_array( $variant, array( 'interactivity', 'router' ), true ) ) {
			return $out;
		}

		$deps = array( '@wordpress/interactivity' );
		if ( 'router' === $variant ) {
			$deps[] = array( 'id' => '@wordpress/interactivity-router', 'import' => 'dynamic' );
		}
		if ( isset( $_GET['afs_pro'] ) && 'first' === $_GET['afs_pro'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$deps[] = 'afs-spike/pro'; // Static dependency: the add-on module is evaluated before this one.
		}
		wp_register_script_module( 'afs-spike/interactivity', plugins_url( 'view.js', __FILE__ ), $deps, '1' );
		wp_enqueue_script_module( 'afs-spike/interactivity' );

		wp_interactivity_state(
			'afsSpike',
			array(
				'restUrl' => esc_url_raw( rest_url( 'afs/v1/results' ) ),
				'router'  => 'router' === $variant,
				'error'   => __( 'Could not load results.', 'ajax-filter-search' ),
			)
		);

		$p = new WP_HTML_Tag_Processor( $out );
		while ( $p->next_tag() ) {
			if ( null !== $p->get_attribute( 'data-afs-config' ) ) {
				$p->set_attribute( 'data-wp-interactive', 'afsSpike' );
				$p->set_attribute( 'data-wp-context', wp_json_encode( array( 'page' => 1, 'busy' => false ) ) );
				$p->set_attribute( 'data-wp-init', 'callbacks.init' );
				$p->set_attribute( 'data-wp-class--afs-loading', 'context.busy' );
				$p->set_attribute( 'data-wp-on--click', 'actions.click' );
				$p->set_attribute( 'data-wp-on--submit', 'actions.submit' );
				$p->set_attribute( 'data-wp-on--input', 'actions.input' );
				$p->set_attribute( 'data-wp-on--change', 'actions.change' );
				if ( 'router' === $variant ) {
					$p->set_attribute( 'data-wp-router-region', 'afs-' . (int) $p->get_attribute( 'data-afs-instance' ) );
				}
			} elseif ( $p->has_class( 'afs-results' ) ) {
				$p->set_attribute( 'data-wp-bind--aria-busy', 'context.busy' );
			}
		}
		return wp_interactivity_process_directives( $p->get_updated_html() );
	},
	10,
	2
);
