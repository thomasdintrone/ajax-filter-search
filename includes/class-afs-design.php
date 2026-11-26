<?php
/**
 * Designs: how a feed looks. Classic is the look of 2.0 and 2.1 and is what sites that already use the plugin keep;
 * Modern is a cleaner card-based look that is the default for new installs. A design is chosen per feed (the `design`
 * shortcode attribute or block setting), else by the Appearance setting, else by the site default.
 *
 * A design is only a stylesheet and a class on the feed: the markup of a Classic feed is exactly the 2.0 markup.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Design {

	const CLASSIC = 'classic';
	const MODERN  = 'modern';
	/** Magazine-style image cards. A working name. */
	const EDITORIAL = 'editorial';
	/** Compact searchable rows. A working name. */
	const LIBRARY = 'library';

	/** Site-wide default, written once when the plugin is activated on a site that has never saved its settings. */
	const OPTION = 'afs_default_design';

	/** Accent of the Modern design on a new install (white text on it passes WCAG AA). */
	const MODERN_ACCENT = '#2563eb';

	/** @return array<string,string> Slug => label: the designs offered in the settings picker and the block. */
	public static function all() {
		return array(
			self::MODERN    => __( 'Modern', 'ajax-filter-search' ),
			self::CLASSIC   => __( 'Classic', 'ajax-filter-search' ),
			self::EDITORIAL => __( 'Editorial Grid', 'ajax-filter-search' ),
			self::LIBRARY   => __( 'Resource Library', 'ajax-filter-search' ),
		);
	}

	/**
	 * Built-in designs with their own item markup (a template per design). Slug => template file.
	 *
	 * @return array<string,string>
	 */
	private static function built_in_templates() {
		return array(
			self::EDITORIAL => 'editorial.php',
			self::LIBRARY   => 'library.php',
		);
	}

	/**
	 * Every design a feed may use: the built-in ones and designs add-ons register with afs_register_design(). Built-in
	 * slugs always win over a registration with the same id.
	 *
	 * @return array<string,string> Slug => label.
	 */
	public static function known() {
		$known = self::all();
		foreach ( afs_registered( 'designs' ) as $slug => $args ) {
			$slug = sanitize_key( $slug );
			if ( '' !== $slug && ! isset( $known[ $slug ] ) ) {
				$known[ $slug ] = isset( $args['label'] ) && is_string( $args['label'] ) ? $args['label'] : $slug;
			}
		}
		return $known;
	}

	public static function is_valid( $slug ) {
		return is_string( $slug ) && isset( self::known()[ $slug ] );
	}

	/** Template file for a design's items. Classic and Modern (and a registered design with no template) use default.php. */
	public static function template_for( $design ) {
		$built_in = self::built_in_templates();
		if ( isset( $built_in[ $design ] ) ) {
			return $built_in[ $design ];
		}
		$args = afs_registered( 'designs' );
		if ( isset( $args[ $design ]['template'] ) && is_string( $args[ $design ]['template'] ) && preg_match( '/^[a-z0-9_-]+\.php$/i', $args[ $design ]['template'] ) ) {
			return $args[ $design ]['template'];
		}
		return 'default.php';
	}

	/** True when a design renders its items with a template of its own (so it needs its options sent with every request). */
	public static function has_own_markup( $design ) {
		return 'default.php' !== self::template_for( $design );
	}

	/** The layout a design with its own markup starts in when the feed does not choose one. */
	public static function default_layout( $design ) {
		return self::LIBRARY === $design ? 'list' : 'grid';
	}

	/** Class for each result item of a design with its own markup. */
	public static function item_class( $design ) {
		if ( self::EDITORIAL === $design ) {
			return 'afs-card';
		}
		if ( self::LIBRARY === $design ) {
			return 'afs-row';
		}
		$args = afs_registered( 'designs' );
		return isset( $args[ $design ]['item_class'] ) && is_string( $args[ $design ]['item_class'] ) ? sanitize_html_class( $args[ $design ]['item_class'] ) : 'afs-item';
	}

	/** Extra class on the feed's root element: only Modern and the designs with their own markup add one. */
	public static function root_class( $design ) {
		if ( self::CLASSIC === $design ) {
			return '';
		}
		$class = ' afs-design-' . sanitize_html_class( $design );
		// The two built-in designs with their own markup share one base stylesheet (controls, chips, states) under this class.
		if ( self::is_discovery( $design ) ) {
			$class .= ' afs-discovery';
		}
		return $class;
	}

	/** True for the built-in Editorial Grid and Resource Library, which share the discovery stylesheet. */
	public static function is_discovery( $design ) {
		return in_array( $design, array( self::EDITORIAL, self::LIBRARY ), true );
	}

	/** The design a site gets when nothing else says otherwise: Classic unless this install chose Modern when it was set up. */
	public static function site_default() {
		return self::MODERN === get_option( self::OPTION, self::CLASSIC ) ? self::MODERN : self::CLASSIC;
	}

	/** Accent colour when none has been saved. */
	public static function default_accent() {
		return self::MODERN === self::site_default() ? self::MODERN_ACCENT : AFSAdmin::DEFAULT_ACCENT;
	}

	/**
	 * The design for a feed.
	 *
	 * @param string $requested Value of the shortcode attribute or block setting (may be empty).
	 * @return string
	 */
	public static function resolve( $requested = '' ) {
		$requested = sanitize_key( (string) $requested );
		if ( self::is_valid( $requested ) ) {
			return $requested;
		}
		$saved = AFSAdmin::afs_get_option( 'afs_appearance_design' );
		return self::is_valid( $saved ) ? $saved : self::site_default();
	}

	/** New installs get Modern; sites that already had the plugin (saved settings, or a recorded choice) keep what they have. */
	public static function activate() {
		if ( false === get_option( self::OPTION ) && false === get_option( AFS_SETTINGS ) ) {
			add_option( self::OPTION, self::MODERN );
			// A fresh install starts on the current look: it has nothing to be told is new.
			AFS_Announcement::mark_fresh_install();
		}
	}

	/**
	 * Text colour that stays readable on a background: white or near-black, whichever contrasts more (WCAG relative
	 * luminance).
	 *
	 * @param string $hex #rgb or #rrggbb.
	 * @return string
	 */
	public static function on_colour( $hex ) {
		$hex = ltrim( (string) sanitize_hex_color( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$lum = 0.0;
		// Red, green and blue weights of relative luminance, each with the offset of its two hex digits.
		foreach ( array( array( 0.2126, 0 ), array( 0.7152, 2 ), array( 0.0722, 4 ) ) as $channel ) {
			$c    = hexdec( substr( $hex, $channel[1], 2 ) ) / 255;
			$lum += $channel[0] * ( $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 ) );
		}
		$white = 1.05 / ( $lum + 0.05 );
		$dark  = ( $lum + 0.05 ) / 0.0597; // Contrast with #111827: its relative luminance is about 0.0097, plus the 0.05 offset.
		return $white >= $dark ? '#ffffff' : '#111827';
	}
}
