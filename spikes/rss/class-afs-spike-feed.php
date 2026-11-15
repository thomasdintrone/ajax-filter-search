<?php
/**
 * SPIKE 3 prototype (not shipped): an RSS 2.0 feed for an AFS filter state, built from the same
 * normalizer and WP_Query as the REST results, rendered with core's own RSS template.
 */
class AFS_Spike_Feed {

	/** @return string RSS 2.0 XML. */
	public static function xml( array $config, array $state ) {
		global $wp_query, $post;

		$saved_query = $wp_query;
		$saved_post  = $post;

		$query          = AFS_Renderer::query( $config, $state ); // same query, same afs_query_args filter, published only.
		$query->is_feed = true;
		$wp_query       = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- spike.

		ob_start();
		// The core template sends a Content-Type header; the test runner has already produced output, so the warning is silenced here only.
		// do_feed_rss2() uses require_once, so a second feed in one process would be empty; load the template directly.
		@load_template( ABSPATH . WPINC . '/feed-rss2.php', false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- spike.
		$xml = (string) ob_get_clean();

		$wp_query = $saved_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- spike.
		$post     = $saved_post;  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- spike.
		return $xml;
	}

	/** GUIDs (permalinks) in feed order. */
	public static function guids( $xml ) {
		$doc = new DOMDocument();
		if ( ! @$doc->loadXML( $xml ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- spike.
			return null;
		}
		$out = array();
		foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
			$out[] = $item->getElementsByTagName( 'guid' )->item( 0 )->textContent;
		}
		return $out;
	}
}
