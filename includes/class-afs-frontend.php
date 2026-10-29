<?php
/**
 * Front end: shortcodes, block, and assets.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Frontend {

	/** @var int Widgets rendered on this request. */
	private static $rendered = 0;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register() {
		add_shortcode( 'ajax_filter_search', array( __CLASS__, 'shortcode' ) );
		// Kept for 1.x pages that used the inner shortcode directly.
		add_shortcode( 'afs_feed', array( __CLASS__, 'feed_shortcode' ) );

		register_block_type(
			AFS_PLUGIN_PATH . 'blocks/feed',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);
	}

	public static function register_assets() {
		wp_register_style( 'afs-styles', AFS_PLUGIN_URL . '/public/css/afs.css', array(), AFS_VERSION );
		wp_register_script( 'afs-script', AFS_PLUGIN_URL . '/public/js/afs.js', array(), AFS_VERSION, true );
	}

	/** Enqueue (once) the CSS and JS, plus the settings the script needs. */
	private static function enqueue_assets() {
		if ( ! wp_style_is( 'afs-styles', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'afs-styles' );
		wp_enqueue_script( 'afs-script' );

		if ( self::$rendered > 0 ) {
			return;
		}

		$accent = sanitize_hex_color( AFSAdmin::afs_retrieve( '_appearance_accent' ) );
		if ( $accent ) {
			wp_add_inline_style( 'afs-styles', '.afs{--afs-accent:' . $accent . ';}' );
		}

		wp_add_inline_script(
			'afs-script',
			'window.afsSettings = ' . wp_json_encode(
				array(
					'restUrl' => esc_url_raw( rest_url( AFS_REST::NAMESPACE_ . '/results' ) ),
					'i18n'    => array(
						'error' => __( 'Sorry, the results could not be loaded. Please try again.', 'ajax-filter-search' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/** Attributes accepted by [ajax_filter_search] (all optional). */
	private static function shortcode_defaults() {
		return array(
			'post_type'      => '',
			'post_tax'       => '',
			'posts_per_page' => '',
			'terms'          => '',
			'filter_type'    => '', // 1.x name for "terms".
			'filter_by'      => '', // Documented in 1.x as a category-slug list.
			'filter_months'  => '',
			'filter_years'   => '',
			'offset'         => '',
			'search'         => '',
			'layout'         => '',
			'orderby'        => '',
			'order'          => '',
			'show_tabs'      => '',
			'show_views'     => '',
			'show_search'    => '',
			'show_months'    => '',
			'show_years'     => '',
		);
	}

	/**
	 * [ajax_filter_search]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( self::shortcode_defaults(), $atts, 'ajax_filter_search' );
		return self::render( $atts );
	}

	/**
	 * Block render callback. Maps camelCase block attributes onto the shortcode names.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$map  = array(
			'postType'   => 'post_type',
			'taxonomy'   => 'post_tax',
			'perPage'    => 'posts_per_page',
			'terms'      => 'terms',
			'layout'     => 'layout',
			'orderby'    => 'orderby',
			'order'      => 'order',
			'showTabs'   => 'show_tabs',
			'showViews'  => 'show_views',
			'showSearch' => 'show_search',
			'showMonths' => 'show_months',
			'showYears'  => 'show_years',
		);
		$atts = array();
		foreach ( $map as $from => $to ) {
			if ( isset( $attributes[ $from ] ) ) {
				$value = $attributes[ $from ];
				if ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				} elseif ( 'show' === $value ) {
					$value = 'true';
				} elseif ( 'hide' === $value ) {
					$value = 'false';
				}
				$atts[ $to ] = $value;
			}
		}
		return self::render( $atts );
	}

	/** Shared by the shortcode and the block. */
	private static function render( $atts ) {
		$config = AFS_Renderer::normalize_config( $atts );

		// Only the first widget on a page reads and writes the address bar.
		$first = 0 === self::$rendered;
		$state = AFS_Renderer::normalize_state( $first ? AFS_Renderer::request_state() : array(), $config );

		self::enqueue_assets();
		$html = AFS_Renderer::render_widget( $config, $state, $first );
		++self::$rendered;

		return $html;
	}

	/** [afs_feed], the 1.x inner shortcode: just the results, no filters. */
	public static function feed_shortcode( $atts ) {
		$atts   = shortcode_atts( self::shortcode_defaults(), $atts, 'afs_feed' );
		$config = AFS_Renderer::normalize_config( $atts );
		$state  = AFS_Renderer::normalize_state( array(), $config );

		self::enqueue_assets();
		$result = AFS_Renderer::render_results( $config, $state, AFS_Renderer::current_url() );
		++self::$rendered;

		return '<div class="afs afs-wrapper afs-layout-' . esc_attr( $state['layout'] ) . '"><div class="afs-results">' . $result['html'] . '</div></div>';
	}
}
