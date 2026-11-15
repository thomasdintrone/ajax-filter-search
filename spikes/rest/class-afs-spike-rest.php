<?php
/**
 * SPIKE 2 prototype: a REST results route that adds JSON `meta`, cache validators and an optional signed config.
 * Lives beside the real route (afs/v1/results is untouched). Not shipped.
 */
class AFS_Spike_Rest {

	const NS = 'afs-spike/v1';

	/** Config fields covered by the signature, in a fixed order. */
	const SIGNED = array( 'post_type', 'post_tax', 'terms', 'posts_per_page', 'orderby', 'order' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$text = array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' );
		register_rest_route(
			self::NS,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_results' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_type'      => $text,
					'post_tax'       => $text,
					'terms'          => $text,
					'posts_per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
					'orderby'        => $text,
					'order'          => $text,
					'afs_s'          => $text,
					'afs_term'       => $text,
					'afs_month'      => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 12 ),
					'afs_year'       => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 9999 ),
					'afs_page'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100000 ),
					'instance'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
					'base_url'       => array( 'type' => 'string', 'format' => 'uri' ),
					'sig'            => array( 'type' => 'string' ),
				),
			)
		);
	}

	/** Canonical string for the signed fields of a request or shortcode config. */
	public static function canonical( array $config ) {
		$out = array();
		foreach ( self::SIGNED as $key ) {
			$out[ $key ] = isset( $config[ $key ] ) ? (string) $config[ $key ] : '';
		}
		return wp_json_encode( $out );
	}

	public static function sign( array $config ) {
		return hash_hmac( 'sha256', self::canonical( $config ), wp_salt( 'auth' ) );
	}

	/** Cheap validator: does not need the query. Changes when any post or term changes, or the plugin updates. */
	public static function etag( $key ) {
		$posts = wp_cache_get_last_changed( 'posts' );
		$terms = wp_cache_get_last_changed( 'terms' );
		return '"' . md5( $key . '|' . $posts . '|' . $terms . '|' . AFS_VERSION ) . '"';
	}

	public static function get_results( $request ) {
		$raw = array();
		foreach ( self::SIGNED as $key ) {
			$raw[ $key ] = $request->get_param( $key );
		}

		$sig = (string) $request->get_param( 'sig' );
		if ( '' !== $sig && ! hash_equals( self::sign( $raw ), $sig ) ) {
			return new WP_Error( 'afs_bad_signature', 'Invalid signature.', array( 'status' => 403 ) );
		}
		$signed = '' !== $sig;

		$config = AFS_Renderer::normalize_config( $raw );
		$state  = AFS_Renderer::normalize_state(
			array(
				's'     => $request->get_param( 'afs_s' ),
				'term'  => $request->get_param( 'afs_term' ),
				'month' => $request->get_param( 'afs_month' ),
				'year'  => $request->get_param( 'afs_year' ),
				'paged' => $request->get_param( 'afs_page' ),
			),
			$config
		);
		$base     = remove_query_arg( AFS_Renderer::URL_KEYS, wp_validate_redirect( esc_url_raw( (string) $request->get_param( 'base_url' ) ), home_url( '/' ) ) );
		$instance = max( 1, (int) $request->get_param( 'instance' ) );

		$key  = md5( wp_json_encode( array( $config, $state, $base, $instance ) ) );
		$etag = self::etag( $key );

		$headers = array(
			'ETag'          => $etag,
			'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300',
		);

		$none = $request->get_header( 'if_none_match' );
		if ( $none && trim( $none ) === $etag ) {
			$res = new WP_REST_Response( null, 304 );
			$res->set_headers( $headers );
			return $res;
		}

		$cache_key = $key . '|' . $etag;
		$data      = wp_cache_get( $cache_key, 'afs_spike' );
		$hit       = false !== $data;
		if ( ! $hit ) {
			$result = AFS_Renderer::render_results( $config, $state, $base, $instance );
			$data   = array(
				'html' => $result['html'],
				'meta' => array(
					'found'    => $result['found'],
					'pages'    => $result['pages'],
					'page'     => $state['paged'],
					'per_page' => $config['per_page'],
					'instance' => $instance,
					'state'    => $state,
				),
			);
			wp_cache_set( $cache_key, $data, 'afs_spike', 300 );
		}
		$data['meta']['signed'] = $signed;
		$data['meta']['cached'] = $hit;

		$res = new WP_REST_Response( $data );
		$res->set_headers( $headers );
		return $res;
	}
}
