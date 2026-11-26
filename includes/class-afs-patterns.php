<?php
/**
 * Block patterns: one ready-made, configured feed for each of the Editorial Grid and Resource Library designs, so a
 * feed can be added from the inserter in one click.
 *
 * A pattern is only block markup for the existing block, so it needs no settings of its own: everything in it is an
 * attribute the block already has, and the visitor can change any of it in the block's sidebar afterwards.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Patterns {

	const CATEGORY = 'ajax-filter-search';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * The patterns: slug => title, description, keywords and the block attributes. Attribute names and values are those
	 * of blocks/feed/block.json ("show" and "hide" for the options that default to the plugin setting).
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		return array(
			'editorial-grid'   => array(
				'title'       => __( 'Editorial Grid: image cards with filters', 'ajax-filter-search' ),
				'description' => __( 'A filterable grid of image cards with category tabs, search, sorting and active filter chips.', 'ajax-filter-search' ),
				'keywords'    => array( 'grid', 'cards', 'blog', 'news', 'filter' ),
				'attributes'  => array(
					'design'    => AFS_Design::EDITORIAL,
					'perPage'   => 6,
					'columns'   => 3,
					'showSort'  => 'show',
					'showChips' => 'show',
					'showViews' => 'show',
				),
			),
			'resource-library' => array(
				'title'       => __( 'Resource Library: searchable list', 'ajax-filter-search' ),
				'description' => __( 'Compact rows with an optional thumbnail, prominent search, sorting and active filter chips.', 'ajax-filter-search' ),
				'keywords'    => array( 'list', 'library', 'resources', 'documents', 'filter' ),
				'attributes'  => array(
					'design'    => AFS_Design::LIBRARY,
					'perPage'   => 10,
					'showSort'  => 'show',
					'showChips' => 'show',
					'showViews' => 'hide',
				),
			),
		);
	}

	/** The block markup of a pattern. */
	public static function content( $attributes ) {
		return '<!-- wp:ajax-filter-search/feed ' . wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES ) . ' /-->';
	}

	public static function register() {
		/**
		 * Return false to leave the block patterns out.
		 *
		 * @param bool $register Default true.
		 */
		if ( ! function_exists( 'register_block_pattern' ) || ! apply_filters( 'afs_register_patterns', true ) ) {
			return;
		}

		register_block_pattern_category(
			self::CATEGORY,
			array( 'label' => __( 'Ajax Filter Search', 'ajax-filter-search' ) )
		);

		foreach ( self::definitions() as $slug => $pattern ) {
			register_block_pattern(
				self::CATEGORY . '/' . $slug,
				array(
					'title'         => $pattern['title'],
					'description'   => $pattern['description'],
					'categories'    => array( self::CATEGORY ),
					'keywords'      => $pattern['keywords'],
					'blockTypes'    => array( 'ajax-filter-search/feed' ),
					'viewportWidth' => 1200,
					'content'       => self::content( $pattern['attributes'] ),
				)
			);
		}
	}
}
