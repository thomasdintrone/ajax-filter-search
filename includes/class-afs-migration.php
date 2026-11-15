<?php
/**
 * Migration helper (roadmap I3): tell an administrator when the active theme carries 1.x-era customizations, and link
 * to the guide. Nothing is changed or removed; template overrides and the 1.x element ids keep working.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Migration {

	const GUIDE_URL = 'https://github.com/thomasdintrone/ajax-filter-search/blob/master/docs/MIGRATION-1.x-to-2.0.md';
	const USER_META = 'afs_migration_notice_dismissed';

	/** Ids from the 1.x markup that the compatibility layer still prints on the first feed. */
	const LEGACY_SELECTORS = '/newsPanelResults|newsViewOptionsPanel|#newsForm|#updateBtn|#resetBtn/';

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
	}

	/**
	 * What the active theme (and its parent) customizes.
	 *
	 * @return array{overrides:string[],css:string[]} Paths relative to wp-content/themes.
	 */
	public static function detect() {
		$found = array(
			'overrides' => array(),
			'css'       => array(),
		);
		$root  = trailingslashit( get_theme_root() );

		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
			$rel = ltrim( str_replace( $root, '', trailingslashit( $dir ) ), '/' );
			foreach ( (array) glob( trailingslashit( $dir ) . 'afs-template/*.php' ) as $file ) {
				$found['overrides'][] = $rel . 'afs-template/' . basename( $file );
			}
			$css = trailingslashit( $dir ) . 'style.css';
			if ( is_readable( $css ) && filesize( $css ) < 1048576 && preg_match( self::LEGACY_SELECTORS, (string) file_get_contents( $css ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, size-limited.
				$found['css'][] = $rel . 'style.css';
			}
		}

		return $found;
	}

	/** A short fingerprint, so the notice comes back if the findings change. */
	private static function fingerprint( $found ) {
		return md5( wp_json_encode( $found ) );
	}

	private static function on_relevant_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->id, array( 'plugins', 'settings_page_' . AFS_MENU_SLUG ), true );
	}

	public static function notice() {
		if ( ! current_user_can( AFS_CAPABILITIES ) || ! self::on_relevant_screen() ) {
			return;
		}
		$found = self::detect();
		if ( ! $found['overrides'] && ! $found['css'] ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), self::USER_META, true ) === self::fingerprint( $found ) ) {
			return;
		}

		$dismiss = wp_nonce_url( add_query_arg( 'afs_dismiss_migration', self::fingerprint( $found ) ), 'afs_dismiss_migration' );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Ajax Filter Search', 'ajax-filter-search' ) . '</strong> ';
		if ( $found['overrides'] ) {
			echo esc_html(
				sprintf(
					/* translators: %s: comma separated file paths. */
					__( 'Your theme overrides the result template (%s). It keeps working.', 'ajax-filter-search' ),
					implode( ', ', $found['overrides'] )
				)
			) . ' ';
		}
		if ( $found['css'] ) {
			echo esc_html(
				sprintf(
					/* translators: %s: comma separated file paths. */
					__( 'Your theme stylesheet (%s) styles element ids from version 1.x.', 'ajax-filter-search' ),
					implode( ', ', $found['css'] )
				)
			) . ' ';
		}
		echo '<a href="' . esc_url( self::GUIDE_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Read the migration guide', 'ajax-filter-search' ) . '</a>';
		echo ' &middot; <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'ajax-filter-search' ) . '</a></p></div>';
	}

	public static function maybe_dismiss() {
		if ( ! isset( $_GET['afs_dismiss_migration'], $_GET['_wpnonce'] ) || ! current_user_can( AFS_CAPABILITIES ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'afs_dismiss_migration' ) ) {
			return;
		}
		// Store the fingerprint of what the notice showed; if the theme changes, the notice returns.
		update_user_meta( get_current_user_id(), self::USER_META, self::fingerprint( self::detect() ) );
		wp_safe_redirect( remove_query_arg( array( 'afs_dismiss_migration', '_wpnonce' ) ) );
		exit;
	}
}
