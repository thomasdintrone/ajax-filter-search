<?php
/**
 * Builds the filter widget and the results fragment.
 *
 * One code path serves the initial page render (shortcode / block) and the
 * REST endpoint, so what the visitor sees is the same with or without JavaScript.
 * Everything that arrives from the browser is validated here.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

class AFS_Renderer {

	/** Query-string keys used for shareable / no-JS state on the first widget of a page. */
	const URL_KEYS = array( 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout' );

	/** @var int Widgets rendered so far on this request. */
	private static $instances = 0;

	/**
	 * Turn raw shortcode/block/REST attributes into a safe, complete config.
	 *
	 * @param array $raw Raw attributes.
	 * @return array
	 */
	public static function normalize_config( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		// Empty shortcode/block attributes mean "use the setting".
		$raw        = array_filter(
			$raw,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
		$post_types = AFSAdmin::viewable_post_types();

		$post_type = isset( $raw['post_type'] ) ? sanitize_key( $raw['post_type'] ) : '';
		if ( ! isset( $post_types[ $post_type ] ) ) {
			$post_type = AFSAdmin::afs_retrieve( '_general_post_type' );
			if ( ! isset( $post_types[ $post_type ] ) ) {
				$post_type = 'post';
			}
		}

		$taxonomy = isset( $raw['post_tax'] ) ? sanitize_key( $raw['post_tax'] ) : sanitize_key( AFSAdmin::afs_retrieve( '_general_post_taxonomy' ) );
		if ( 'none' === $taxonomy || '' === $taxonomy || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			$taxonomy = '';
		}

		// "terms" is the clear name; filter_type (1.0.x) and filter_by (documented in the 1.0.x readme) mean the same thing.
		$terms = array();
		foreach ( array( 'terms', 'filter_type', 'filter_by' ) as $attr ) {
			if ( ! empty( $raw[ $attr ] ) ) {
				$terms = is_array( $raw[ $attr ] ) ? $raw[ $attr ] : explode( ',', (string) $raw[ $attr ] );
				break;
			}
		}
		$terms = array_values( array_unique( array_filter( array_map( 'sanitize_title', $terms ) ) ) );
		$terms = array_values( array_diff( $terms, array( 'all' ) ) );
		if ( '' === $taxonomy ) {
			$terms = array();
		}

		$per_page = isset( $raw['posts_per_page'] ) ? absint( $raw['posts_per_page'] ) : 0;
		if ( ! $per_page ) {
			$per_page = absint( AFSAdmin::afs_retrieve( '_general_posts_per_page' ) );
		}
		$per_page = min( 100, max( 1, $per_page ? $per_page : 10 ) );

		$orderby = isset( $raw['orderby'] ) ? sanitize_key( $raw['orderby'] ) : 'date';
		$orderby = in_array( $orderby, array( 'date', 'title', 'modified' ), true ) ? $orderby : 'date';
		$order   = ( isset( $raw['order'] ) && 'ASC' === strtoupper( (string) $raw['order'] ) ) ? 'ASC' : 'DESC';

		$layout = isset( $raw['layout'] ) ? sanitize_key( $raw['layout'] ) : AFSAdmin::afs_retrieve( '_general_layout' );
		$layout = 'grid' === $layout ? 'grid' : 'list';

		$bool = static function ( $key, $default ) use ( $raw ) {
			return isset( $raw[ $key ] ) && '' !== $raw[ $key ] ? wp_validate_boolean( $raw[ $key ] ) : $default;
		};

		return array(
			'post_type'   => $post_type,
			'taxonomy'    => $taxonomy,
			'terms'       => $terms,
			'per_page'    => $per_page,
			'orderby'     => $orderby,
			'order'       => $order,
			'layout'      => $layout,
			'show_tabs'   => $bool( 'show_tabs', 1 === (int) AFSAdmin::afs_retrieve( '_general_show_filters' ) ),
			'show_views'  => $bool( 'show_views', 1 === (int) AFSAdmin::afs_retrieve( '_general_views' ) ),
			'show_search' => $bool( 'show_search', true ),
			'show_months' => $bool( 'show_months', true ),
			'show_years'  => $bool( 'show_years', true ),
			// Starting values for the filters.
			'search'      => isset( $raw['search'] ) ? sanitize_text_field( $raw['search'] ) : '',
			'month'       => isset( $raw['filter_months'] ) ? self::clean_month( $raw['filter_months'] ) : 0,
			'year'        => isset( $raw['filter_years'] ) ? self::clean_year( $raw['filter_years'] ) : 0,
			'page'        => isset( $raw['offset'] ) ? max( 1, absint( $raw['offset'] ) ) : 1,
		);
	}

	/**
	 * Validate the visitor's current filter choices against a config.
	 *
	 * @param array $raw    Keys: s, term, month, year, paged, layout.
	 * @param array $config Normalized config (supplies the defaults).
	 * @return array
	 */
	public static function normalize_state( $raw, $config ) {
		$raw = is_array( $raw ) ? $raw : array();

		$term = isset( $raw['term'] ) ? sanitize_title( $raw['term'] ) : '';
		if ( '' === $config['taxonomy'] || 'all' === $term ) {
			$term = '';
		} elseif ( $config['terms'] && ! in_array( $term, $config['terms'], true ) ) {
			$term = '';
		}

		$layout = isset( $raw['layout'] ) ? sanitize_key( $raw['layout'] ) : $config['layout'];

		return array(
			's'      => isset( $raw['s'] ) ? sanitize_text_field( $raw['s'] ) : $config['search'],
			'term'   => $term,
			'month'  => isset( $raw['month'] ) ? self::clean_month( $raw['month'] ) : $config['month'],
			'year'   => isset( $raw['year'] ) ? self::clean_year( $raw['year'] ) : $config['year'],
			'paged'  => isset( $raw['paged'] ) ? max( 1, absint( $raw['paged'] ) ) : $config['page'],
			'layout' => in_array( $layout, array( 'list', 'grid' ), true ) ? $layout : $config['layout'],
		);
	}

	private static function clean_month( $value ) {
		$value = absint( $value );
		return ( $value >= 1 && $value <= 12 ) ? $value : 0;
	}

	private static function clean_year( $value ) {
		$value = absint( $value );
		return ( $value >= 1000 && $value <= 9999 ) ? $value : 0;
	}

	/**
	 * The query for a config + state.
	 *
	 * @return WP_Query
	 */
	public static function query( $config, $state ) {
		$args = array(
			'post_type'           => $config['post_type'],
			'post_status'         => 'publish',
			'posts_per_page'      => $config['per_page'],
			'paged'               => $state['paged'],
			'orderby'             => $config['orderby'],
			'order'               => $config['order'],
			'ignore_sticky_posts' => true,
		);

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
		 * @param array $args   Query arguments.
		 * @param array $config Normalized widget config.
		 * @param array $state  Normalized visitor state.
		 */
		$args = apply_filters( 'afs_query_args', $args, $config, $state );

		return new WP_Query( $args );
	}

	/** Terms shown as tabs (WP_Term[]). */
	private static function tab_terms( $config ) {
		if ( ! $config['taxonomy'] ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy' => $config['taxonomy'],
				'orderby'  => 'term_id',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$list = array();
		foreach ( $terms as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			if ( $config['terms'] && ! in_array( $term->slug, $config['terms'], true ) ) {
				continue;
			}
			$list[] = $term;
		}
		return $list;
	}

	/** Years that have published posts of a type, newest first. */
	private static function years( $post_type ) {
		global $wpdb;

		$key   = 'afs_years_' . $post_type . '_' . wp_cache_get_last_changed( 'posts' );
		$years = wp_cache_get( $key, 'afs' );
		if ( false === $years ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Cached just below; no core API lists archive years for one post type.
			$years = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY 1 DESC",
					$post_type
				)
			);
			$years = array_map( 'absint', $years );
			wp_cache_set( $key, $years, 'afs', DAY_IN_SECONDS );
		}
		return $years;
	}

	/**
	 * A landmark label that stays unique when several feeds share a page.
	 *
	 * @param string $label    Base label.
	 * @param int    $instance 1-based feed number on the page.
	 * @return string
	 */
	private static function label( $label, $instance ) {
		return $instance > 1 ? sprintf( '%s %d', $label, $instance ) : $label;
	}

	/** The page URL without this plugin's query args. */
	public static function current_url() {
		global $wp;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only filter state.
		$query = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' );
		$url   = add_query_arg( $query, user_trailingslashit( home_url( $wp->request ) ) );
		return remove_query_arg( self::URL_KEYS, $url );
	}

	/** Visitor state from the query string (used for the first widget on a page). */
	public static function request_state() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public, read-only filter state; validated in normalize_state().
		$get = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' );
		// phpcs:enable
		$state = array();
		foreach ( array(
			'afs_s'      => 's',
			'afs_term'   => 'term',
			'afs_month'  => 'month',
			'afs_year'   => 'year',
			'afs_page'   => 'paged',
			'afs_layout' => 'layout',
		) as $param => $key ) {
			if ( isset( $get[ $param ] ) && is_scalar( $get[ $param ] ) ) {
				$state[ $key ] = $get[ $param ];
			}
		}
		return $state;
	}

	/** URL for a state: only non-default bits are added. */
	private static function url_for( $base, $state, $overrides = array() ) {
		$state = array_merge( $state, $overrides );
		$args  = array();
		if ( '' !== $state['s'] ) {
			$args['afs_s'] = $state['s'];
		}
		if ( $state['term'] ) {
			$args['afs_term'] = $state['term'];
		}
		if ( $state['month'] ) {
			$args['afs_month'] = $state['month'];
		}
		if ( $state['year'] ) {
			$args['afs_year'] = $state['year'];
		}
		if ( $state['paged'] > 1 ) {
			$args['afs_page'] = $state['paged'];
		}
		return add_query_arg( $args, $base );
	}

	/**
	 * Full widget: filters plus the first page of results.
	 *
	 * @param array $config Normalized config.
	 * @param array $state  Normalized state.
	 * @param bool  $syncs_url Whether this widget mirrors its state into the address bar.
	 * @return string HTML.
	 */
	public static function render_widget( $config, $state, $syncs_url = false ) {
		++self::$instances;
		$n    = self::$instances;
		$id   = 1 === $n ? 'afs-wrapper' : 'afs-wrapper-' . $n;
		$base = self::current_url();

		$client = array(
			'post_type'      => $config['post_type'],
			'post_tax'       => $config['taxonomy'],
			'terms'          => implode( ',', $config['terms'] ),
			'posts_per_page' => $config['per_page'],
			'orderby'        => $config['orderby'],
			'order'          => $config['order'],
			'layout'         => $config['layout'],
		);

		$tabs = array();
		if ( $config['show_tabs'] ) {
			$tabs = self::tab_terms( $config );
			if ( count( $tabs ) < 2 && ! ( 1 === count( $tabs ) && ! $config['terms'] ) ) {
				$tabs = array();
			}
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="afs afs-wrapper afs-layout-<?php echo esc_attr( $state['layout'] ); ?>" data-afs-config="<?php echo esc_attr( wp_json_encode( $client ) ); ?>" data-afs-instance="<?php echo (int) $n; ?>" data-afs-url="<?php echo $syncs_url ? '1' : '0'; ?>">
			<h2 class="afs-sr-only"><?php echo esc_html( self::label( __( 'Filterable posts', 'ajax-filter-search' ), $n ) ); ?></h2>
			<form class="afs-form" method="get" action="<?php echo esc_url( $base ); ?>" role="search" aria-label="<?php echo esc_attr( self::label( __( 'Filter posts', 'ajax-filter-search' ), $n ) ); ?>">
				<?php
				// Plain-permalink sites keep their page id in the query string.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				foreach ( array( 'p', 'page_id' ) as $keep ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET[ $keep ] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $keep ), esc_attr( absint( wp_unslash( $_GET[ $keep ] ) ) ) );
					}
				}
				?>
				<input type="hidden" name="afs_term" value="<?php echo esc_attr( $state['term'] ); ?>" class="afs-term-input">

				<?php if ( $tabs ) : ?>
					<nav class="afs-Tabs" aria-label="<?php echo esc_attr( self::label( __( 'Categories', 'ajax-filter-search' ), $n ) ); ?>">
						<ul class="afs-CommonTabs">
							<?php
							self::tab_item(
								'',
								__( 'All', 'ajax-filter-search' ),
								'' === $state['term'],
								self::url_for(
									$base,
									$state,
									array(
										'term'  => '',
										'paged' => 1,
									)
								)
							);
							foreach ( $tabs as $term ) {
								self::tab_item(
									$term->slug,
									$term->name,
									$term->slug === $state['term'],
									self::url_for(
										$base,
										$state,
										array(
											'term'  => $term->slug,
											'paged' => 1,
										)
									)
								);
							}
							?>
						</ul>
					</nav>
				<?php endif; ?>

				<div class="afs-Filters">
					<?php if ( $config['show_search'] ) : ?>
						<div class="afs-field afs-field-search">
							<label for="<?php echo esc_attr( $id ); ?>-s"><?php esc_html_e( 'Search', 'ajax-filter-search' ); ?></label>
							<input type="search" id="<?php echo esc_attr( $id ); ?>-s" name="afs_s" value="<?php echo esc_attr( $state['s'] ); ?>" autocomplete="off">
						</div>
					<?php endif; ?>

					<?php if ( $config['show_months'] ) : ?>
						<div class="afs-field">
							<label for="<?php echo esc_attr( $id ); ?>-month"><?php esc_html_e( 'Month', 'ajax-filter-search' ); ?></label>
							<select id="<?php echo esc_attr( $id ); ?>-month" name="afs_month">
								<option value=""><?php esc_html_e( 'All Months', 'ajax-filter-search' ); ?></option>
								<?php
								global $wp_locale;
								for ( $m = 1; $m <= 12; $m++ ) {
									printf( '<option value="%d"%s>%s</option>', (int) $m, selected( $state['month'], $m, false ), esc_html( $wp_locale->get_month( $m ) ) );
								}
								?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ( $config['show_years'] ) : ?>
						<div class="afs-field">
							<label for="<?php echo esc_attr( $id ); ?>-year"><?php esc_html_e( 'Year', 'ajax-filter-search' ); ?></label>
							<select id="<?php echo esc_attr( $id ); ?>-year" name="afs_year">
								<option value=""><?php esc_html_e( 'All Years', 'ajax-filter-search' ); ?></option>
								<?php
								foreach ( self::years( $config['post_type'] ) as $year ) {
									printf( '<option value="%d"%s>%d</option>', (int) $year, selected( $state['year'], $year, false ), (int) $year );
								}
								?>
							</select>
						</div>
					<?php endif; ?>

					<div class="afs-actions">
						<button type="submit" class="afs-btn afs-btn-primary afs-update"><?php esc_html_e( 'Update', 'ajax-filter-search' ); ?></button>
						<button type="button" class="afs-btn afs-reset"><?php esc_html_e( 'Reset', 'ajax-filter-search' ); ?></button>
					</div>
				</div>

				<?php if ( $config['show_views'] ) : ?>
					<div class="afs-Switch" role="group" aria-label="<?php echo esc_attr( self::label( __( 'Layout', 'ajax-filter-search' ), $n ) ); ?>">
						<button type="button" class="afs-btn afs-switch-btn" data-afs-layout="list" aria-pressed="<?php echo 'list' === $state['layout'] ? 'true' : 'false'; ?>"><?php esc_html_e( 'List View', 'ajax-filter-search' ); ?></button>
						<button type="button" class="afs-btn afs-switch-btn" data-afs-layout="grid" aria-pressed="<?php echo 'grid' === $state['layout'] ? 'true' : 'false'; ?>"><?php esc_html_e( 'Grid View', 'ajax-filter-search' ); ?></button>
					</div>
				<?php endif; ?>
			</form>

			<div class="afs-results" aria-live="polite" aria-busy="false">
				<?php echo self::render_results( $config, $state, $base, $n )['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in render_results(). ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function tab_item( $slug, $label, $active, $href ) {
		printf(
			'<li class="%1$s"><a href="%2$s" data-afs-term="%3$s"%4$s>%5$s</a></li>',
			$active ? 'active' : '',
			esc_url( $href ),
			esc_attr( $slug ),
			$active ? ' aria-current="true"' : '',
			esc_html( $label )
		);
	}

	/**
	 * The results fragment: items, count and pagination.
	 *
	 * @param array  $config Normalized config.
	 * @param array  $state  Normalized state.
	 * @param string $base   Page URL used for the pagination links.
	 * @param int    $instance 1-based feed number on the page (keeps landmark labels unique).
	 * @return array{html:string,found:int,pages:int}
	 */
	public static function render_results( $config, $state, $base, $instance = 1 ) {
		$query = self::query( $config, $state );
		$found = (int) $query->found_posts;
		$pages = (int) $query->max_num_pages;

		if ( ! defined( 'FILTER_TYPE' ) ) {
			// Back-compat for theme template overrides written for 1.x.
			define( 'FILTER_TYPE', implode( ',', $config['terms'] ) );
		}

		ob_start();

		if ( $query->have_posts() ) {
			echo '<div class="afs-TableHeader" aria-hidden="true"><div class="afs-col-date">' . esc_html__( 'Date', 'ajax-filter-search' ) . '</div><div class="afs-col-title">' . esc_html__( 'Headline', 'ajax-filter-search' ) . '</div></div>';
			echo '<div class="afs-items">';
			$i = 1;
			while ( $query->have_posts() ) {
				$query->the_post();
				printf(
					'<article class="%1$s" itemscope itemtype="https://schema.org/BlogPosting">%2$s</article>',
					esc_attr( implode( ' ', get_post_class( 'afs-TableRowItem afs-item gridPR' . $i ) ) ),
					return_template( 'default.php', $config['terms'], $config['taxonomy'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is trusted theme/plugin markup.
				);
				++$i;
			}
			echo '</div>';
			wp_reset_postdata();

			$first = ( ( $state['paged'] - 1 ) * $config['per_page'] ) + 1;
			$last  = min( $found, $state['paged'] * $config['per_page'] );
			printf(
				'<p class="afs-count">%s</p>',
				sprintf(
					/* translators: 1: first result number, 2: last result number, 3: total results. */
					esc_html__( 'Displaying %1$s to %2$s (of %3$s)', 'ajax-filter-search' ),
					'<span class="afs-first">' . esc_html( number_format_i18n( $first ) ) . '</span>',
					'<span class="afs-last">' . esc_html( number_format_i18n( $last ) ) . '</span>',
					'<span class="afs-total">' . esc_html( number_format_i18n( $found ) ) . '</span>'
				) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pieces are escaped above.
			);
			echo self::pagination( $pages, $state, $base, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pagination().
		} else {
			echo '<p class="afs-none">' . esc_html__( 'No results found.', 'ajax-filter-search' ) . '</p>';
		}

		return array(
			'html'  => ob_get_clean(),
			'found' => $found,
			'pages' => $pages,
		);
	}

	/** Numbered pagination with a sliding window. */
	private static function pagination( $total, $state, $base, $instance = 1 ) {
		if ( $total < 2 ) {
			return '';
		}

		$current = min( $state['paged'], $total );
		$window  = 4;
		$min     = max( 1, $current - $window );
		$max     = min( $total, $current + $window );

		$out = '<nav class="afs-pagination post-nav" aria-label="' . esc_attr( self::label( __( 'Pagination', 'ajax-filter-search' ), $instance ) ) . '"><ul class="pagination">';

		$link = static function ( $page, $label, $class = '', $current_page = false, $title = '' ) use ( $state, $base ) {
			return sprintf(
				'<li><a class="afs-btn %1$s" href="%2$s" data-afs-page="%3$d"%4$s%5$s>%6$s</a></li>',
				esc_attr( $class ),
				esc_url( self::url_for( $base, $state, array( 'paged' => $page ) ) ),
				(int) $page,
				$current_page ? ' aria-current="page"' : '',
				$title ? ' aria-label="' . esc_attr( $title ) . '"' : '',
				$label // Already escaped by caller.
			);
		};

		if ( $current > 1 ) {
			$out .= $link( $current - 1, '<span aria-hidden="true">&lsaquo;</span>', 'afs-prev', false, __( 'Previous page', 'ajax-filter-search' ) );
		}
		for ( $p = $min; $p <= $max; $p++ ) {
			$out .= $link( $p, esc_html( number_format_i18n( $p ) ), $p === $current ? 'afs-btn-primary' : '', $p === $current );
		}
		if ( $current < $total ) {
			$out .= $link( $current + 1, '<span aria-hidden="true">&rsaquo;</span>', 'afs-next', false, __( 'Next page', 'ajax-filter-search' ) );
		}

		return $out . '</ul></nav>';
	}
}
