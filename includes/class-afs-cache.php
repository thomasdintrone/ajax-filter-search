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

	/** Raise when cached output must be thrown away on update, whatever else has or has not changed. */
	const EPOCH = 2;

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
			add_action( $hook, array( __CLASS__, 'mark_image_meta' ), 10, 3 );
		}
		add_action( 'update_option_' . AFS_SETTINGS, array( __CLASS__, 'mark' ) );
		add_action( 'switched_theme', array( __CLASS__, 'mark' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );
	}

	/** Note that something changed; the option is written once, at shutdown. */
	public static function mark() {
		self::$dirty = true;
	}

	/** The post meta keys behind what a card shows of an image: which one is featured, where the file is, and its sizes. */
	const IMAGE_META = array( '_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata' );

	/**
	 * Mark a change when the meta behind a featured image changes: choosing or removing the featured image, replacing the
	 * file, or regenerating or cropping its sizes (which rewrites the attachment metadata without touching any post).
	 *
	 * @param int    $meta_id    Meta ID (unused).
	 * @param int    $object_id  Post ID (unused).
	 * @param string $meta_key   Meta key.
	 */
	public static function mark_image_meta( $meta_id, $object_id, $meta_key ) {
		if ( in_array( $meta_key, self::IMAGE_META, true ) ) {
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
		// The epoch changes when what is cached changes meaning (for example when protected content stopped depending on a
		// visitor's cookie), so an update purges output cached by an earlier version: the object cache is keyed by this
		// string and so are the validators browsers and caches send back. Shared caches in front of the site keep an old
		// response for at most the response's own max-age (60 seconds by default) plus its stale window (5 minutes).
		$version = self::EPOCH . '.' . get_option( self::OPTION, '0' );
		/**
		 * Filters the cache version used in result validators and cached results. Change it to invalidate everything.
		 *
		 * @param string $version Counter, changes when posts, terms, settings or the theme change.
		 */
		return (string) apply_filters( 'afs_cache_version', $version );
	}
}
