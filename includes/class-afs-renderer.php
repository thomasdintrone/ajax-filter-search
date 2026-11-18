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
	const URL_KEYS = array( 'afs_s', 'afs_term', 'afs_month', 'afs_year', 'afs_page', 'afs_layout', 'afs_sort' );

	/** Sort choices a visitor can pick: key => array( orderby, order ). */
	const SORTS = array(
		'newest'   => array( 'date', 'DESC' ),
		'oldest'   => array( 'date', 'ASC' ),
		'title'    => array( 'title', 'ASC' ),
		'modified' => array( 'modified', 'DESC' ),
		'comments' => array( 'comment_count', 'DESC' ),
	);

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
		$terms = array_values( array_unique( array_filter( array_map( 'sanitize_title', array_slice( $terms, 0, 50 ) ) ) ) );
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
			// Opt-in helpers (off unless the shortcode, block or setting turns them on, so existing output is unchanged).
			'show_sort'   => $bool( 'show_sort', 1 === (int) AFSAdmin::afs_retrieve( '_general_show_sort' ) ),
			'show_chips'  => $bool( 'show_chips', 1 === (int) AFSAdmin::afs_retrieve( '_general_show_chips' ) ),
			// classic (the 2.0 look) or modern; the markup is the same, only a class and a stylesheet differ.
			'design'      => AFS_Design::resolve( isset( $raw['design'] ) ? $raw['design'] : '' ),
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
	 * @param array $raw    Keys: s, term, month, year, paged, layout, sort.
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
		$sort   = isset( $raw['sort'] ) ? sanitize_key( $raw['sort'] ) : '';

		return array(
			's'      => isset( $raw['s'] ) ? sanitize_text_field( $raw['s'] ) : $config['search'],
			'term'   => $term,
			'month'  => isset( $raw['month'] ) ? self::clean_month( $raw['month'] ) : $config['month'],
			'year'   => isset( $raw['year'] ) ? self::clean_year( $raw['year'] ) : $config['year'],
			'paged'  => isset( $raw['paged'] ) ? min( 100000, max( 1, absint( $raw['paged'] ) ) ) : $config['page'],
			'layout' => in_array( $layout, array( 'list', 'grid' ), true ) ? $layout : $config['layout'],
			// '' means the feed's own order; anything that is not a known choice is ignored.
			'sort'   => isset( self::SORTS[ $sort ] ) ? $sort : '',
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
		return AFS_Query::run( $config, $state );
	}

	/** Terms shown as tabs (WP_Term[]). */
	private static function tab_terms( $config ) {
		if ( ! $config['taxonomy'] ) {
			return array();
		}
		// Cached per taxonomy and invalidated whenever any term changes (core bumps the terms change counter).
		$cache_key = 'afs_tabs_' . $config['taxonomy'] . '_' . wp_cache_get_last_changed( 'terms' );
		$terms     = wp_cache_get( $cache_key, 'afs' );
		if ( false === $terms ) {
			$terms = get_terms(
				array(
					'taxonomy' => $config['taxonomy'],
					'orderby'  => 'term_id',
				)
			);
			if ( is_wp_error( $terms ) ) {
				return array();
			}
			wp_cache_set( $cache_key, $terms, 'afs', DAY_IN_SECONDS );
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
			'afs_sort'   => 'sort',
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
		if ( ! empty( $state['sort'] ) ) {
			$args['afs_sort'] = $state['sort'];
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
		$n  = self::$instances;
		$id = 1 === $n ? 'afs-wrapper' : 'afs-wrapper-' . $n;
		// 1.x element IDs, only on the first feed (IDs must be unique). Part of the compatibility layer.
		$legacy = 1 === $n && AFS_Compat::enabled();
		$base   = self::current_url();

		$client = array(
			'post_type'      => $config['post_type'],
			'post_tax'       => $config['taxonomy'],
			'terms'          => implode( ',', $config['terms'] ),
			'posts_per_page' => $config['per_page'],
			'orderby'        => $config['orderby'],
			'order'          => $config['order'],
			'layout'         => $config['layout'],
		);
		// Only when on, so the markup of every existing feed is unchanged. The script passes it on to the REST route.
		if ( $config['show_chips'] ) {
			$client['show_chips'] = '1';
		}

		$tabs = array();
		if ( $config['show_tabs'] ) {
			$tabs = self::tab_terms( $config );
			if ( count( $tabs ) < 2 && ! ( 1 === count( $tabs ) && ! $config['terms'] ) ) {
				$tabs = array();
			}
		}

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="afs afs-wrapper afs-layout-<?php echo esc_attr( $state['layout'] ); ?><?php echo AFS_Design::MODERN === $config['design'] ? ' afs-design-modern' : ''; ?>" data-afs-config="<?php echo esc_attr( wp_json_encode( $client ) ); ?>" data-afs-instance="<?php echo (int) $n; ?>" data-afs-url="<?php echo $syncs_url ? '1' : '0'; ?>">
			<h2 class="afs-sr-only"><?php echo esc_html( self::label( __( 'Filterable posts', 'ajax-filter-search' ), $n ) ); ?></h2>
			<form<?php echo $legacy ? ' id="newsForm"' : ''; ?> class="afs-form" method="get" action="<?php echo esc_url( $base ); ?>" role="search" aria-label="<?php echo esc_attr( self::label( __( 'Filter posts', 'ajax-filter-search' ), $n ) ); ?>">
				<?php
				// Plain-permalink sites keep their page id in the query string.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				foreach ( array( 'p', 'page_id' ) as $keep ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET[ $keep ] ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						printf( '<input type="hidden" name="%s" value="%d">', esc_attr( $keep ), absint( wp_unslash( $_GET[ $keep ] ) ) );
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

					<?php if ( $config['show_sort'] ) : ?>
						<div class="afs-field afs-field-sort">
							<label for="<?php echo esc_attr( $id ); ?>-sort"><?php esc_html_e( 'Sort by', 'ajax-filter-search' ); ?></label>
							<?php echo self::sort_select( $id . '-sort', $config, $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in sort_select(). ?>
						</div>
					<?php endif; ?>

					<div class="afs-actions">
						<button type="submit"<?php echo $legacy ? ' id="updateBtn"' : ''; ?> class="afs-btn afs-btn-primary afs-update"><?php esc_html_e( 'Update', 'ajax-filter-search' ); ?></button>
						<button type="button"<?php echo $legacy ? ' id="resetBtn"' : ''; ?> class="afs-btn afs-reset"><?php esc_html_e( 'Reset', 'ajax-filter-search' ); ?></button>
					</div>
				</div>

				<?php if ( $config['show_views'] ) : ?>
					<div<?php echo $legacy ? ' id="newsViewOptionsPanel"' : ''; ?> class="afs-Switch" role="group" aria-label="<?php echo esc_attr( self::label( __( 'Layout', 'ajax-filter-search' ), $n ) ); ?>">
						<button type="button" class="afs-btn afs-switch-btn" data-afs-layout="list" aria-pressed="<?php echo 'list' === $state['layout'] ? 'true' : 'false'; ?>"><?php esc_html_e( 'List View', 'ajax-filter-search' ); ?></button>
						<button type="button" class="afs-btn afs-switch-btn" data-afs-layout="grid" aria-pressed="<?php echo 'grid' === $state['layout'] ? 'true' : 'false'; ?>"><?php esc_html_e( 'Grid View', 'ajax-filter-search' ); ?></button>
					</div>
				<?php endif; ?>
			</form>

			<div<?php echo $legacy ? ' id="newsPanelResults"' : ''; ?> class="afs-results" aria-live="polite" aria-busy="false">
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

		AFS_Compat::define_filter_type( $config['terms'] );

		ob_start();

		if ( $config['show_chips'] ) {
			echo self::chips( $config, $state, $base, $instance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in chips().
		}

		if ( $query->have_posts() ) {
			echo '<div class="afs-TableHeader" aria-hidden="true"><div class="afs-col-date">' . esc_html__( 'Date', 'ajax-filter-search' ) . '</div><div class="afs-col-title">' . esc_html__( 'Headline', 'ajax-filter-search' ) . '</div></div>';
			echo '<div class="afs-items">';
			$i = 1;
			while ( $query->have_posts() ) {
				$query->the_post();
				printf(
					'<article class="%1$s" itemscope itemtype="https://schema.org/BlogPosting">%2$s</article>',
					esc_attr( implode( ' ', array_diff( get_post_class( 'afs-TableRowItem afs-item gridPR' . $i ), array( 'hentry' ) ) ) ), // 'hentry' makes many themes style each result as a full post.
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
			if ( $config['show_chips'] && self::has_active_filters( $state ) ) {
				printf(
					'<p class="afs-recover"><a class="afs-btn" href="%1$s" data-afs-clear="all">%2$s</a></p>',
					esc_url( self::url_for( $base, $state, self::cleared_filters() ) ),
					esc_html__( 'Clear all filters', 'ajax-filter-search' )
				);
			}
		}

		return array(
			'html'  => ob_get_clean(),
			'found' => $found,
			'pages' => $pages,
		);
	}

	/** Labels for the sort choices. */
	private static function sort_labels() {
		return array(
			'newest'   => __( 'Newest', 'ajax-filter-search' ),
			'oldest'   => __( 'Oldest', 'ajax-filter-search' ),
			'title'    => __( 'A to Z', 'ajax-filter-search' ),
			'modified' => __( 'Recently updated', 'ajax-filter-search' ),
			'comments' => __( 'Most commented', 'ajax-filter-search' ),
		);
	}

	/** The sort choice that equals the feed's own order, or '' when it matches none of them. */
	private static function default_sort( $config ) {
		foreach ( self::SORTS as $key => $pair ) {
			if ( $pair[0] === $config['orderby'] && $pair[1] === $config['order'] ) {
				return $key;
			}
		}
		return '';
	}

	/** The sort select. data-default lets the script leave the default out of the address bar. */
	private static function sort_select( $id, $config, $state ) {
		$default  = self::default_sort( $config );
		$selected = $state['sort'] ? $state['sort'] : $default;
		$html     = sprintf( '<select id="%1$s" name="afs_sort" data-default="%2$s">', esc_attr( $id ), esc_attr( $default ) );
		if ( '' === $default ) {
			$html .= sprintf( '<option value=""%s>%s</option>', selected( $selected, '', false ), esc_html__( 'Default order', 'ajax-filter-search' ) );
		}
		foreach ( self::sort_labels() as $key => $label ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	private static function has_active_filters( $state ) {
		return '' !== $state['s'] || $state['term'] || $state['month'] || $state['year'];
	}

	/** State overrides that clear every filter (the sort and layout are not filters and are kept). */
	private static function cleared_filters() {
		return array(
			's'     => '',
			'term'  => '',
			'month' => 0,
			'year'  => 0,
			'paged' => 1,
		);
	}

	/**
	 * Active filters as removable chips, plus Clear all. Each chip is a plain link, so it works without JavaScript;
	 * the script turns a click into a fetch.
	 */
	private static function chips( $config, $state, $base, $instance ) {
		if ( ! self::has_active_filters( $state ) ) {
			return '';
		}

		global $wp_locale;
		$chips = array();
		if ( '' !== $state['s'] ) {
			/* translators: %s: the visitor's search text. */
			$chips['s'] = sprintf( __( 'Search: %s', 'ajax-filter-search' ), $state['s'] );
		}
		if ( $state['term'] ) {
			$term          = $config['taxonomy'] ? get_term_by( 'slug', $state['term'], $config['taxonomy'] ) : false;
			$chips['term'] = $term ? $term->name : $state['term'];
		}
		if ( $state['month'] ) {
			$chips['month'] = $wp_locale->get_month( $state['month'] );
		}
		if ( $state['year'] ) {
			$chips['year'] = (string) $state['year'];
		}

		$empty = array(
			's'     => '',
			'term'  => '',
			'month' => 0,
			'year'  => 0,
		);
		$out   = '<div class="afs-chips" role="group" aria-label="' . esc_attr( self::label( __( 'Active filters', 'ajax-filter-search' ), $instance ) ) . '">';
		foreach ( $chips as $key => $label ) {
			$out .= sprintf(
				'<a class="afs-chip" href="%1$s" data-afs-clear="%2$s" aria-label="%3$s">%4$s <span aria-hidden="true">&times;</span></a>',
				esc_url(
					self::url_for(
						$base,
						$state,
						array(
							$key    => $empty[ $key ],
							'paged' => 1,
						)
					)
				),
				esc_attr( $key ),
				/* translators: %s: the filter, for example "Category: News". */
				esc_attr( sprintf( __( 'Remove filter: %s', 'ajax-filter-search' ), $label ) ),
				esc_html( $label )
			);
		}
		$out .= sprintf(
			'<a class="afs-chip-clear" href="%1$s" data-afs-clear="all">%2$s</a></div>',
			esc_url( self::url_for( $base, $state, self::cleared_filters() ) ),
			esc_html__( 'Clear all', 'ajax-filter-search' )
		);
		return $out;
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
