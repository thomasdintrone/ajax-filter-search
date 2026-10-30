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
	}

	public static function register_routes() {
		$text = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		register_rest_route(
			self::NAMESPACE_,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_results' ),
				'permission_callback' => '__return_true',
				'args'                => array(
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
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_results( $request ) {
		$config = AFS_Renderer::normalize_config(
			array(
				'post_type'      => $request->get_param( 'post_type' ),
				'post_tax'       => $request->get_param( 'post_tax' ),
				'terms'          => $request->get_param( 'terms' ),
				'posts_per_page' => $request->get_param( 'posts_per_page' ),
				'orderby'        => $request->get_param( 'orderby' ),
				'order'          => $request->get_param( 'order' ),
			)
		);

		$state = AFS_Renderer::normalize_state(
			array(
				's'     => $request->get_param( 'afs_s' ),
				'term'  => $request->get_param( 'afs_term' ),
				'month' => $request->get_param( 'afs_month' ),
				'year'  => $request->get_param( 'afs_year' ),
				'paged' => $request->get_param( 'afs_page' ),
			),
			$config
		);

		// Links in the fragment are built from the caller's page URL, but only if it is on this site.
		$base = wp_validate_redirect( esc_url_raw( (string) $request->get_param( 'base_url' ) ), home_url( '/' ) );
		$base = remove_query_arg( AFS_Renderer::URL_KEYS, $base );

		$result = AFS_Renderer::render_results( $config, $state, $base, max( 1, (int) $request->get_param( 'instance' ) ) );

		return rest_ensure_response(
			array(
				'html'  => $result['html'],
				'found' => $result['found'],
				'pages' => $result['pages'],
			)
		);
	}
}
