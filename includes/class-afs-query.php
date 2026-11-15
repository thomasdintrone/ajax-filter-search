<?php
/**
 * The single place that builds and runs the feed query.
 *
 * HTML, REST and any future output (for example a feed) must go through here so every output applies the same
 * visibility rules. Published content is enforced AFTER the `afs_query_args` filter runs, so a site or add-on
 * filter can change ordering, add taxonomy or meta clauses and so on, but cannot expose unpublished content.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Query {

	/**
	 * WP_Query arguments for a normalized config and state.
	 *
	 * @param array $config Normalized widget config.
	 * @param array $state  Normalized visitor state.
	 * @return array
	 */
	public static function args( $config, $state ) {
		$args = array(
			'post_type'           => $config['post_type'],
			'post_status'         => 'publish',
			'posts_per_page'      => $config['per_page'],
			'paged'               => $state['paged'],
			'orderby'             => $config['orderby'],
			'order'               => $config['order'],
			'ignore_sticky_posts' => true,
		);

		// A visitor's sort choice (opt-in control) overrides the feed's own order.
		if ( ! empty( $state['sort'] ) && isset( AFS_Renderer::SORTS[ $state['sort'] ] ) ) {
			list( $args['orderby'], $args['order'] ) = AFS_Renderer::SORTS[ $state['sort'] ];
		}

		if ( '' !== $state['s'] ) {
			$args['s'] = $state['s'];
		}

		$date_query = array();
		if ( $state['year'] ) {
			$date_query['year'] = $state['year'];
		}
		if ( $state['month'] ) {
			$date_query['month'] = $state['month'];
		}
		if ( $date_query ) {
			$args['date_query'] = array( $date_query );
		}

		$slugs = $state['term'] ? array( $state['term'] ) : $config['terms'];
		if ( $config['taxonomy'] && $slugs ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by term is the feature; the query is bounded to one taxonomy.
			$args['tax_query'] = array(
				array(
					'taxonomy' => $config['taxonomy'],
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			);
		}

		/**
		 * Filters the WP_Query arguments for a feed.
		 *
		 * `post_status` and `perm` are reset after this filter: feeds only ever list published content.
		 *
		 * @param array $args   Query arguments.
		 * @param array $config Normalized widget config.
		 * @param array $state  Normalized visitor state.
		 */
		$args = apply_filters( 'afs_query_args', $args, $config, $state );
		$args = is_array( $args ) ? $args : array();

		$args['post_status'] = 'publish';
		unset( $args['perm'] );

		return $args;
	}

	/**
	 * @param array $config Normalized widget config.
	 * @param array $state  Normalized visitor state.
	 * @return WP_Query
	 */
	public static function run( $config, $state ) {
		return new WP_Query( self::args( $config, $state ) );
	}
}
