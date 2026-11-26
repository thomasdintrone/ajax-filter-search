<?php
/**
 * Public, read-only REST endpoint that returns a results fragment.
 *
 * GET /wp-json/afs/v1/results
 *
 * It replaces the 1.x admin-ajax action. Only published content of public post
 * types is ever returned, and every parameter is validated in AFS_Renderer.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_REST {

	const NAMESPACE_ = 'afs/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_not_modified' ), 10, 3 );
	}

	/** A 304 has no body: headers are already sent by the server, so stop it from printing `null`. */
	public static function serve_not_modified( $served, $result, $request ) {
		if ( $result instanceof WP_REST_Response && 304 === $result->get_status() && 0 === strpos( (string) $request->get_route(), '/' . self::NAMESPACE_ . '/' ) ) {
			return true;
		}
		return $served;
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_results' ),
				'permission_callback' => '__return_true',
				'args'                => self::route_args(),
			)
		);
	}

	/**
	 * Validated request parameters shared by the results route and the feed route.
	 *
	 * @return array
	 */
	public static function route_args() {
		$text = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		return array(
			'post_type'      => $text,
			'post_tax'       => $text,
			'terms'          => $text,
			'posts_per_page' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			'orderby'        => $text,
			'order'          => $text,
			'afs_s'          => $text,
			'afs_term'       => $text,
			'afs_sort'       => $text,
			'show_chips'     => $text,
			'design'         => $text,
			'show_image'     => $text,
			'image_ratio'    => $text,
			'columns'        => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 4,
			),
			'show_excerpt'   => $text,
			'excerpt_length' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 200,
			),
			'show_date'      => $text,
			'show_time'      => $text,
			'show_category'  => $text,
			'afs_month'      => array(
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 12,
			),
			'afs_year'       => array(
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 9999,
			),
			'afs_page'       => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100000,
			),
			'instance'       => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 50,
			),
			'base_url'       => array(
				'type'   => 'string',
				'format' => 'uri',
			),
		);
	}

	/** @return array{0:array,1:array} Normalized config and state for a request. */
	public static function config_and_state( $request ) {
		$config = AFS_Renderer::normalize_config(
			array(
				'post_type'      => $request->get_param( 'post_type' ),
				'post_tax'       => $request->get_param( 'post_tax' ),
				'terms'          => $request->get_param( 'terms' ),
				'posts_per_page' => $request->get_param( 'posts_per_page' ),
				'orderby'        => $request->get_param( 'orderby' ),
				'order'          => $request->get_param( 'order' ),
				'show_chips'     => $request->get_param( 'show_chips' ),
				'design'         => $request->get_param( 'design' ),
				'show_image'     => $request->get_param( 'show_image' ),
				'image_ratio'    => $request->get_param( 'image_ratio' ),
				'columns'        => $request->get_param( 'columns' ),
				'show_excerpt'   => $request->get_param( 'show_excerpt' ),
				'excerpt_length' => $request->get_param( 'excerpt_length' ),
				'show_date'      => $request->get_param( 'show_date' ),
				'show_time'      => $request->get_param( 'show_time' ),
				'show_category'  => $request->get_param( 'show_category' ),
			)
		);

		$state = AFS_Renderer::normalize_state(
			array(
				's'     => $request->get_param( 'afs_s' ),
				'term'  => $request->get_param( 'afs_term' ),
				'month' => $request->get_param( 'afs_month' ),
				'year'  => $request->get_param( 'afs_year' ),
				'paged' => $request->get_param( 'afs_page' ),
				'sort'  => $request->get_param( 'afs_sort' ),
			),
			$config
		);

		return array( $config, $state );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_results( $request ) {
		list( $config, $state ) = self::config_and_state( $request );

		// Links in the fragment are built from the caller's page URL, but only if it is on this site.
		$base = wp_validate_redirect( esc_url_raw( (string) $request->get_param( 'base_url' ) ), home_url( '/' ) );
		$base = remove_query_arg( AFS_Renderer::URL_KEYS, $base );

		$instance = max( 1, (int) $request->get_param( 'instance' ) );

		// Anonymous responses can be cached and revalidated; a signed-in request may be personalised by a site's own
		// filters, so it is never cached or stored. Sites can opt out with the filter.
		$cacheable = ! is_user_logged_in() && (bool) apply_filters( 'afs_rest_cacheable', true, $config, $state );
		$key       = md5( wp_json_encode( array( $config, $state, $base, $instance ) ) );
		$etag      = self::etag( $key );

		if ( $cacheable ) {
			if ( self::matches( (string) $request->get_header( 'if_none_match' ), $etag ) ) {
				$response = new WP_REST_Response( null, 304 );
				self::cache_headers( $response, $etag );
				return $response;
			}
			$data = wp_cache_get( $key . '|' . $etag, 'afs' );
		} else {
			$data = false;
		}

		if ( false === $data || ! is_array( $data ) ) {
			$result = AFS_Renderer::render_results( $config, $state, $base, $instance );
			$data   = array(
				'html'  => $result['html'],
				'found' => $result['found'],
				'pages' => $result['pages'],
				'meta'  => array(
					'found'    => $result['found'],
					'pages'    => $result['pages'],
					'page'     => $state['paged'],
					'per_page' => $config['per_page'],
					'instance' => $instance,
					'state'    => array(
						's'     => $state['s'],
						'term'  => $state['term'],
						'month' => $state['month'],
						'year'  => $state['year'],
						'sort'  => $state['sort'],
					),
				),
			);
			if ( $cacheable ) {
				wp_cache_set( $key . '|' . $etag, $data, 'afs', 5 * MINUTE_IN_SECONDS );
			}
		}

		/**
		 * Filters the results response before it is sent. Keep `html`, `found` and `pages`.
		 *
		 * @param array $data   Response data.
		 * @param array $config Normalized widget config.
		 * @param array $state  Normalized visitor state.
		 */
		$data = apply_filters( 'afs_rest_response', $data, $config, $state );

		$response = rest_ensure_response( $data );
		if ( $cacheable ) {
			self::cache_headers( $response, $etag );
		} else {
			$response->header( 'Cache-Control', 'private, no-cache' );
		}
		return $response;
	}

	/**
	 * Cheap validator that needs no query: changes whenever AFS_Cache notes a post, term, settings or theme change, or
	 * the plugin is updated. A published, edited, trashed or re-termed post therefore invalidates every cached result.
	 */
	public static function etag( $key ) {
		return '"' . md5( $key . '|' . AFS_Cache::version() . '|' . AFS_VERSION ) . '"';
	}

	/**
	 * Does an If-None-Match header match our validator? Servers often rewrite a validator when they compress the
	 * body (Apache's mod_deflate turns "abc" into "abc-gzip"), so a weak prefix and a content-coding suffix are ignored.
	 * A list of validators and the wildcard are honoured.
	 *
	 * @param string $header If-None-Match request header.
	 * @param string $etag   Our validator, quoted.
	 * @return bool
	 */
	public static function matches( $header, $etag ) {
		$header = trim( $header );
		if ( '' === $header ) {
			return false;
		}
		if ( '*' === $header ) {
			return true;
		}
		foreach ( explode( ',', $header ) as $candidate ) {
			$candidate = preg_replace( '/^W\//', '', trim( $candidate ) );
			$candidate = preg_replace( '/-(gzip|br|deflate|zstd)"$/', '"', $candidate );
			if ( $candidate === $etag ) {
				return true;
			}
		}
		return false;
	}

	public static function cache_headers( $response, $etag ) {
		$response->header( 'ETag', $etag );
		/**
		 * Filters the Cache-Control value of an anonymous results response. Return '' to send none.
		 *
		 * @param string $value Default: public, one minute, stale-while-revalidate five.
		 */
		$value = (string) apply_filters( 'afs_rest_cache_control', 'public, max-age=60, stale-while-revalidate=300' );
		if ( '' !== $value ) {
			$response->header( 'Cache-Control', $value );
		}
	}
}
