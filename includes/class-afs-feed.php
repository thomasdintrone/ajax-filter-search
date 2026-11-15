<?php
/**
 * An RSS 2.0 feed for any filter state: GET /wp-json/afs/v1/feed
 *
 * Takes the same validated parameters as the results route, runs the same query (AFS_Query, so published-only and
 * the afs_query_args filter apply), and renders with WordPress's own RSS 2.0 template so escaping and structure are
 * core's. Core feed URLs cannot express every state (see docs/decisions/0004-rss-feed-endpoint.md).
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Feed {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve' ), 20, 3 );
	}

	public static function register_routes() {
		/**
		 * Filters whether the RSS feed route is available. Return false to remove it.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'afs_enable_feed', true ) ) {
			return;
		}

		// The feed has no layout or instance, so it takes the results route's parameters minus those two.
		$args = AFS_REST::route_args();
		unset( $args['instance'], $args['base_url'], $args['show_chips'] );

		register_rest_route(
			AFS_REST::NAMESPACE_,
			'/feed',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_feed' ),
				'permission_callback' => '__return_true',
				'args'                => $args,
			)
		);
	}

	/**
	 * URL of the feed for a config and state (as returned by AFS_Renderer::normalize_config / normalize_state).
	 *
	 * @param array $config Normalized config.
	 * @param array $state  Normalized state.
	 * @return string
	 */
	public static function url( $config, $state ) {
		$args = array(
			'post_type'      => $config['post_type'],
			'post_tax'       => $config['taxonomy'],
			'terms'          => implode( ',', $config['terms'] ),
			'posts_per_page' => $config['per_page'],
			'orderby'        => $config['orderby'],
			'order'          => $config['order'],
			'afs_s'          => $state['s'],
			'afs_term'       => $state['term'],
			'afs_month'      => $state['month'],
			'afs_year'       => $state['year'],
			'afs_page'       => $state['paged'],
			'afs_sort'       => $state['sort'],
		);
		if ( $state['paged'] <= 1 ) {
			unset( $args['afs_page'] );
		}
		// Empty filters are left out so the URL stays short and readable.
		$args = array_filter(
			$args,
			static function ( $v ) {
				return '' !== $v && 0 !== $v && null !== $v;
			}
		);
		return add_query_arg( rawurlencode_deep( $args ), rest_url( AFS_REST::NAMESPACE_ . '/feed' ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_feed( $request ) {
		list( $config, $state ) = AFS_REST::config_and_state( $request );

		$cacheable = ! is_user_logged_in() && (bool) apply_filters( 'afs_rest_cacheable', true, $config, $state );
		$etag      = AFS_REST::etag( 'feed|' . md5( wp_json_encode( array( $config, $state ) ) ) );

		if ( $cacheable ) {
			if ( AFS_REST::matches( (string) $request->get_header( 'if_none_match' ), $etag ) ) {
				$response = new WP_REST_Response( null, 304 );
				AFS_REST::cache_headers( $response, $etag );
				return $response;
			}
		}

		$response = new WP_REST_Response( self::xml( $config, $state ) );
		$response->header( 'Content-Type', 'application/rss+xml; charset=' . get_option( 'blog_charset' ) );
		if ( $cacheable ) {
			AFS_REST::cache_headers( $response, $etag );
		} else {
			$response->header( 'Cache-Control', 'private, no-cache' );
		}
		return $response;
	}

	/** The REST server would JSON-encode a string; print the XML instead (status and headers are already sent). */
	public static function serve( $served, $result, $request ) {
		if ( $served || ! ( $result instanceof WP_REST_Response ) || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		if ( '/' . AFS_REST::NAMESPACE_ . '/feed' !== $request->get_route() ) {
			return $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML built and escaped by core's RSS template.
		return true;
	}

	/**
	 * RSS 2.0 XML for a config and state.
	 *
	 * @param array $config Normalized config.
	 * @param array $state  Normalized state.
	 * @return string
	 */
	public static function xml( $config, $state ) {
		global $wp_query, $post;

		$saved_query = $wp_query;
		$saved_post  = $post;

		$query          = AFS_Query::run( $config, $state );
		$query->is_feed = true;
		$wp_query       = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The core feed template reads the main query; restored below.

		ob_start();
		try {
			// Core's template sends a Content-Type header itself; the REST server sets the final one, and when output
			// has already started (command-line tests) PHP would warn, so that single warning is silenced.
			@load_template( ABSPATH . WPINC . '/feed-rss2.php', false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			$xml      = (string) ob_get_clean();
			$wp_query = $saved_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post     = $saved_post;  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $xml;
	}
}
