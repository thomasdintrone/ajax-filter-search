<?php
/**
 * Storage for what add-ons register. See api.php and docs/extending.md.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Registry {

	/** Registry types an add-on may fill. */
	const TYPES = array( 'fields', 'designs', 'template_parts', 'admin_tabs', 'rest_meta' );

	/** @var array<string,array<string,array>> */
	private static $items = array();

	public static function init() {
		add_action(
			'init',
			static function () {
				/**
				 * Fires when add-ons should register fields, designs, template parts, admin tabs and REST meta.
				 */
				do_action( 'afs_register' );
			},
			5
		);
	}

	/**
	 * @param string $type One of self::TYPES.
	 * @param string $id   Unique key (lowercase letters, numbers, dashes and underscores).
	 * @param array  $args Free-form definition owned by the registrant.
	 * @return bool True when stored; false for an unknown type, an invalid id or a duplicate.
	 */
	public static function register( $type, $id, $args = array() ) {
		$id = sanitize_key( $id );
		if ( ! in_array( $type, self::TYPES, true ) || '' === $id || isset( self::$items[ $type ][ $id ] ) ) {
			return false;
		}
		self::$items[ $type ][ $id ] = is_array( $args ) ? $args : array();
		return true;
	}

	public static function unregister( $type, $id ) {
		unset( self::$items[ $type ][ sanitize_key( $id ) ] );
	}

	/** @return array|null */
	public static function get( $type, $id ) {
		return self::$items[ $type ][ sanitize_key( $id ) ] ?? null;
	}

	/** @return array<string,array> */
	public static function all( $type ) {
		$items = in_array( $type, self::TYPES, true ) ? ( self::$items[ $type ] ?? array() ) : array();
		/**
		 * Filters the registered items of one type.
		 *
		 * @param array  $items Registered items keyed by id.
		 * @param string $type  Registry type.
		 */
		return (array) apply_filters( 'afs_registry_items', $items, $type );
	}

	/** Test helper: forget everything. */
	public static function reset() {
		self::$items = array();
	}
}
