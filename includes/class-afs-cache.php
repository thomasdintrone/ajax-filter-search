<?php
/**
 * A change counter for validators and cached results.
 *
 * Core's wp_cache_get_last_changed() is only stable with a persistent object cache; without one it is regenerated on
 * every request, so an ETag built from it would never match. This counter lives in one autoloaded option, is bumped at
 * most once per request, and only when published content, terms or the plugin's own settings could have changed.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Cache {

	const OPTION = 'afs_cache_version';

	/** @var bool */
	private static $dirty = false;

	public static function init() {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'transition_post_status', 'set_object_terms', 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'mark' ) );
		}
		// A card shows the featured image, so changing one (even without saving the post) or editing or removing an
		// attachment must refresh cached results.
		foreach ( array( 'edit_attachment', 'delete_attachment' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'mark' ) );
		}
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'mark_thumbnail' ), 10, 3 );
		}
		add_action( 'update_option_' . AFS_SETTINGS, array( __CLASS__, 'mark' ) );
		add_action( 'switched_theme', array( __CLASS__, 'mark' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );
	}

	/** Note that something changed; the option is written once, at shutdown. */
	public static function mark() {
		self::$dirty = true;
	}

	/**
	 * Mark a change when the post meta that holds a featured image changes.
	 *
	 * @param int    $meta_id    Meta ID (unused).
	 * @param int    $object_id  Post ID (unused).
	 * @param string $meta_key   Meta key.
	 */
	public static function mark_thumbnail( $meta_id, $object_id, $meta_key ) {
		if ( '_thumbnail_id' === $meta_key ) {
			self::mark();
		}
	}

	public static function flush() {
		if ( self::$dirty ) {
			self::$dirty = false;
			update_option( self::OPTION, (string) microtime( true ), true );
		}
	}

	/**
	 * Current counter. Sites with their own invalidation needs can append to it.
	 *
	 * @return string
	 */
	public static function version() {
		$version = get_option( self::OPTION, '0' );
		/**
		 * Filters the cache version used in result validators and cached results. Change it to invalidate everything.
		 *
		 * @param string $version Counter, changes when posts, terms, settings or the theme change.
		 */
		return (string) apply_filters( 'afs_cache_version', $version );
	}
}
