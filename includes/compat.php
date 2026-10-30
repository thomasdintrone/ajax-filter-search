<?php
/**
 * Compatibility layer for code written against 1.x.
 *
 * Everything here is deprecated, small, and switched off in one place:
 *
 *     add_filter( 'afs_enable_legacy_compat', '__return_false' );
 *
 * None of it builds or runs shortcodes from submitted data; the legacy ajax
 * action goes through the same validation and renderer as the REST endpoint.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

// 3. Constants that existed in 1.x.
if ( ! defined( 'AFS_RELEASE' ) ) {
	define( 'AFS_RELEASE', 'July 20, 2016' );
}
if ( ! defined( 'AFS_CALLBACK' ) ) {
	define( 'AFS_CALLBACK', 'afs_options_page' );
}
if ( ! defined( 'AFS_PLUGIN_ADMIN_URL' ) ) {
	define( 'AFS_PLUGIN_ADMIN_URL', plugins_url( 'admin/', AFS_PLUGIN_PATH . 'ajax-filter-search.php' ) );
}

// 2. The 1.x bootstrap class and function.
if ( ! class_exists( 'AFSSettings' ) ) {
	/** Empty stand-in for the 1.x bootstrap class. */
	class AFSSettings {}
}
if ( ! function_exists( 'AFSinit' ) ) {
	/** Returns the (empty) 1.x bootstrap object, as 1.x did. */
	function AFSinit() {
		global $afs;
		if ( ! isset( $afs ) ) {
			$afs = new AFSSettings();
		}
		return $afs;
	}
}
AFSinit();

// 1. Functions that 1.x registered as the shortcode callbacks.
if ( ! function_exists( 'afs_feed' ) ) {
	/**
	 * 1.x callback for [afs_feed]. Deprecated: use do_shortcode().
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Unused.
	 * @return string
	 */
	function afs_feed( $atts = array(), $content = null ) {
		return AFS_Frontend::feed_shortcode( $atts );
	}
}
if ( ! function_exists( 'ajax_filter_search' ) ) {
	/**
	 * 1.x callback for [ajax_filter_search]. Deprecated: use do_shortcode().
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Unused.
	 * @return string
	 */
	function ajax_filter_search( $atts = array(), $content = null ) {
		return AFS_Frontend::shortcode( $atts );
	}
}

/**
 * Runtime parts of the compatibility layer.
 */
class AFS_Compat {

	/** Whether the layer is on (default yes). */
	public static function enabled() {
		/**
		 * Filters whether the 1.x compatibility layer is active.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'afs_enable_legacy_compat', true );
	}

	public static function init() {
		// 4. Old script/style handles, so code that depends on them keeps working.
		add_action( 'init', array( __CLASS__, 'register_handles' ), 20 );

		// 5. The 1.x ajax action.
		add_action( 'wp_ajax_my_action', array( __CLASS__, 'ajax_results' ) );
		add_action( 'wp_ajax_nopriv_my_action', array( __CLASS__, 'ajax_results' ) );
	}

	public static function register_handles() {
		if ( ! self::enabled() ) {
			return;
		}
		// Aliases: depending on them loads the current script; the old icon font is gone.
		wp_register_script( 'afs-script-js', false, array( 'afs-script' ), AFS_VERSION, true );
		wp_register_style( 'afs-fontawesome', false, array(), AFS_VERSION );
	}

	/**
	 * 7. 1.x printed `ajaxurl` and `scriptpath` on every page. They are now only
	 * defined on pages that show a feed, and only if nothing else defined them.
	 *
	 * @return string JavaScript, or '' when off.
	 */
	public static function legacy_globals_js() {
		if ( ! self::enabled() || ! apply_filters( 'afs_legacy_globals', true ) ) {
			return '';
		}
		return 'window.ajaxurl = window.ajaxurl || ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';'
			. 'window.scriptpath = window.scriptpath || ' . wp_json_encode( site_url() ) . ';';
	}

	/** A submitted value as a plain string; missing or array-typed input becomes ''. */
	private static function post_value( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public, read-only listing (same as the REST endpoint).
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by AFS_Renderer.
		return trim( wp_unslash( (string) $_POST[ $key ] ) );
	}

	/**
	 * 5. admin-ajax action `my_action` as 1.x scripts call it (cached pages and
	 * old JavaScript keep working after an upgrade). Read-only; returns the
	 * current results markup. Same validation and renderer as the REST route.
	 */
	public static function ajax_results() {
		if ( ! self::enabled() ) {
			wp_die( '0', '', array( 'response' => 200 ) );
		}

		$filing = self::post_value( 'filingType' );
		$config = AFS_Renderer::normalize_config(
			array(
				// 1.x sent a tab slug, a comma list (the "All" tab of a restricted feed), or "all".
				'terms' => 'all' === $filing ? '' : $filing,
			)
		);
		$state  = AFS_Renderer::normalize_state(
			array(
				's'     => self::post_value( 'filterBy' ),
				'month' => self::post_value( 'filterMonths' ),
				'year'  => self::post_value( 'filterYears' ),
				'paged' => self::post_value( 'page' ),
			),
			$config
		);

		$base = wp_validate_redirect( esc_url_raw( (string) wp_get_referer() ), home_url( '/' ) );
		$base = remove_query_arg( AFS_Renderer::URL_KEYS, $base );

		$result = AFS_Renderer::render_results( $config, $state, $base );

		header( 'X-AFS-Deprecated: the my_action ajax action is deprecated; use the afs/v1/results REST route' );
		echo $result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AFS_Renderer.
		exit;
	}
}
