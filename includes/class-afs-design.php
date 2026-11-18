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

	/** Site-wide default, written once when the plugin is activated on a site that has never saved its settings. */
	const OPTION = 'afs_default_design';

	/** Accent of the Modern design on a new install (white text on it passes WCAG AA). */
	const MODERN_ACCENT = '#2563eb';

	/** @return array<string,string> Slug => label. */
	public static function all() {
		return array(
			self::MODERN  => __( 'Modern', 'ajax-filter-search' ),
			self::CLASSIC => __( 'Classic', 'ajax-filter-search' ),
		);
	}

	public static function is_valid( $slug ) {
		return is_string( $slug ) && isset( self::all()[ $slug ] );
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
