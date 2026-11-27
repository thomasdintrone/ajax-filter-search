<?php
/**
 * Settings page and option access.
 *
 * The class keeps its 1.x name and its afs_retrieve() / afs_get_option()
 * helpers because theme template overrides may call them.
 *
 * @package ajax-filter-search
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AFSAdmin' ) ) :

	class AFSAdmin {

		const SECTION_GENERAL    = 'afs_general';
		const SECTION_APPEARANCE = 'afs_appearance';
		const SECTION_CARDS      = 'afs_cards';
		const DEFAULT_ACCENT     = '#f1b45c';

		/**
		 * Field definitions. Keys are the stored option keys (unchanged from 1.x).
		 *
		 * @return array<string,array>
		 */
		public static function fields() {
			return array(
				'afs_general_post_type'        => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'post_type',
					'label'   => __( 'Post type', 'ajax-filter-search' ),
					'help'    => __( 'Select a post type to pull the feed from.', 'ajax-filter-search' ),
					'default' => 'post',
				),
				'afs_general_post_taxonomy'    => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'taxonomy',
					'label'   => __( 'Taxonomy', 'ajax-filter-search' ),
					'help'    => __( 'Select a taxonomy for the tabbed filters.', 'ajax-filter-search' ),
					'default' => 'category',
				),
				'afs_general_posts_per_page'   => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'number',
					'label'   => __( 'Posts per page', 'ajax-filter-search' ),
					'help'    => __( 'How many posts to display per page (1 to 100).', 'ajax-filter-search' ),
					'default' => 10,
				),
				'afs_general_show_filters'     => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Category tabs', 'ajax-filter-search' ),
					'help'    => __( 'Display tabbed filters.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_general_views'            => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Layout switcher', 'ajax-filter-search' ),
					'help'    => __( 'Show the List View / Grid View switcher.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_general_layout'           => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'layout',
					'label'   => __( 'Default layout', 'ajax-filter-search' ),
					'help'    => __( 'The layout used when a page first loads.', 'ajax-filter-search' ),
					'default' => 'list',
				),
				'afs_general_show_sort'        => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Sort control', 'ajax-filter-search' ),
					'help'    => __( 'Let visitors sort by newest, oldest, A to Z, recently updated or most commented. Each feed can override this.', 'ajax-filter-search' ),
					'default' => 0,
				),
				'afs_general_show_chips'       => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Active filter chips', 'ajax-filter-search' ),
					'help'    => __( 'Show the active filters as removable chips with a Clear all link, and offer a way out when nothing matches. Each feed can override this.', 'ajax-filter-search' ),
					'default' => 0,
				),
				'afs_general_noindex_filtered' => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Hide filtered views from search engines', 'ajax-filter-search' ),
					'help'    => __( 'Adds noindex to a page when it is shown with a filter, search or page number in the address, so only the main page is indexed.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_appearance_design'        => array(
					'section' => self::SECTION_APPEARANCE,
					'type'    => 'design',
					'label'   => __( 'Design', 'ajax-filter-search' ),
					'help'    => __( 'How feeds look. Editorial Grid and Resource Library show image cards or compact rows; what they show is set under Cards. You can also choose a design for a single feed with the design attribute or the block setting.', 'ajax-filter-search' ),
					'default' => AFS_Design::site_default(),
				),
				'afs_cards_show_image'         => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'yesno',
					'label'   => __( 'Featured image', 'ajax-filter-search' ),
					'help'    => __( 'Show each post\'s featured image. A post without one gets a tinted tile with a letter.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_cards_image_ratio'        => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'ratio',
					'label'   => __( 'Image shape', 'ajax-filter-search' ),
					'help'    => __( 'Editorial Grid images only. Resource Library thumbnails are always square.', 'ajax-filter-search' ),
					'default' => '16:10',
					'needs'   => 'afs_cards_show_image',
				),
				'afs_cards_columns'            => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'columns',
					'label'   => __( 'Columns', 'ajax-filter-search' ),
					'help'    => __( 'The most columns the Editorial Grid uses. Fewer on narrow screens.', 'ajax-filter-search' ),
					'default' => 3,
				),
				'afs_cards_show_excerpt'       => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'yesno',
					'label'   => __( 'Excerpt', 'ajax-filter-search' ),
					'help'    => __( 'Show a short excerpt under each title.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_cards_excerpt_length'     => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'words',
					'label'   => __( 'Excerpt length', 'ajax-filter-search' ),
					'help'    => __( 'How many words of the excerpt to show (10 to 80).', 'ajax-filter-search' ),
					'default' => 25,
					'needs'   => 'afs_cards_show_excerpt',
				),
				'afs_cards_show_date'          => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'yesno',
					'label'   => __( 'Publication date', 'ajax-filter-search' ),
					'help'    => __( 'Show the date each post was published.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_cards_show_time'          => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'yesno',
					'label'   => __( 'Include the time', 'ajax-filter-search' ),
					'help'    => __( 'Show the time of day next to the date. It needs the publication date to be shown.', 'ajax-filter-search' ),
					'default' => 0,
					'needs'   => 'afs_cards_show_date',
				),
				'afs_cards_show_category'      => array(
					'section' => self::SECTION_CARDS,
					'type'    => 'yesno',
					'label'   => __( 'Category badge', 'ajax-filter-search' ),
					'help'    => __( 'Show the post\'s category as a badge, using the taxonomy chosen above.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_appearance_accent'        => array(
					'section' => self::SECTION_APPEARANCE,
					'type'    => 'color',
					'label'   => __( 'Accent color', 'ajax-filter-search' ),
					'help'    => __( 'Used for links, the active tab and the active page.', 'ajax-filter-search' ),
					'default' => AFS_Design::default_accent(),
				),
			);
		}

		/** Defaults keyed by option key. */
		public static function defaults() {
			$defaults = array();
			foreach ( self::fields() as $key => $field ) {
				$defaults[ $key ] = $field['default'];
			}
			return $defaults;
		}

		/**
		 * Retrieve a setting using the short 1.x key, e.g. '_general_post_type'.
		 *
		 * @param string $val Key suffix.
		 * @return mixed
		 */
		public static function afs_retrieve( $val ) {
			return self::afs_get_option( AFS_SUB . $val );
		}

		/**
		 * Retrieve a setting by its full option key, falling back to the default.
		 *
		 * @param string $option_name Full key, e.g. 'afs_general_post_type'.
		 * @return mixed False when the key is unknown.
		 */
		public static function afs_get_option( $option_name = '' ) {
			$defaults = self::defaults();
			$options  = get_option( AFS_SETTINGS, array() );
			$options  = wp_parse_args( is_array( $options ) ? $options : array(), $defaults );

			return isset( $options[ $option_name ] ) ? $options[ $option_name ] : false;
		}

		/** Hook everything up. */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_admin_scripts' ) );
			add_filter( 'plugin_action_links_' . AFS_PLUGIN_FILE, array( __CLASS__, 'plugin_settings_link' ) );
		}

		public static function add_admin_menu() {
			add_options_page( AFS_PAGE_TITLE, AFS_MENU_TAB_TITLE, AFS_CAPABILITIES, AFS_MENU_SLUG, array( __CLASS__, 'options_page' ) );
		}

		public static function plugin_settings_link( $links ) {
			$url = admin_url( 'options-general.php?page=' . AFS_MENU_SLUG );
			array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ajax-filter-search' ) . '</a>' );
			return $links;
		}

		public static function register_settings() {
			register_setting(
				'afs_settings_group',
				AFS_SETTINGS,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'default'           => self::defaults(),
				)
			);

			add_settings_section(
				self::SECTION_GENERAL,
				__( 'General', 'ajax-filter-search' ),
				array( __CLASS__, 'general_section_intro' ),
				'afs_settings_page'
			);
			add_settings_section(
				self::SECTION_APPEARANCE,
				__( 'Appearance', 'ajax-filter-search' ),
				'__return_null',
				'afs_settings_page'
			);
			add_settings_section(
				self::SECTION_CARDS,
				__( 'Cards', 'ajax-filter-search' ),
				'__return_null',
				'afs_settings_page'
			);

			foreach ( self::fields() as $key => $field ) {
				add_settings_field(
					$key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					'afs_settings_page',
					$field['section'],
					array_merge(
						$field,
						array(
							'key'       => $key,
							'label_for' => 'afs-' . $key,
						)
					)
				);
			}
		}

		public static function general_section_intro() {
			echo '<p>' . wp_kses(
				__( 'Customize your settings below, then add the shortcode <code>[ajax_filter_search]</code> (or the "Ajax Filter Search" block) to the page where you want the feed to appear.', 'ajax-filter-search' ),
				array( 'code' => array() )
			) . '</p>';
		}

		/**
		 * Validate everything on save. Unknown keys are dropped.
		 *
		 * @param mixed $input Raw submitted value.
		 * @return array
		 */
		public static function sanitize( $input ) {
			$input = is_array( $input ) ? wp_unslash( $input ) : array();
			$out   = self::defaults();

			$post_types = self::viewable_post_types();
			$post_type  = isset( $input['afs_general_post_type'] ) ? sanitize_key( $input['afs_general_post_type'] ) : 'post';
			if ( ! isset( $post_types[ $post_type ] ) ) {
				$post_type = 'post';
			}
			$out['afs_general_post_type'] = $post_type;

			$taxonomy = isset( $input['afs_general_post_taxonomy'] ) ? sanitize_key( $input['afs_general_post_taxonomy'] ) : 'none';
			if ( 'none' !== $taxonomy && ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$taxonomy = 'none';
			}
			$out['afs_general_post_taxonomy'] = $taxonomy;

			$per_page                          = isset( $input['afs_general_posts_per_page'] ) ? absint( $input['afs_general_posts_per_page'] ) : 10;
			$out['afs_general_posts_per_page'] = min( 100, max( 1, $per_page ) );

			foreach ( array( 'afs_general_show_filters', 'afs_general_views', 'afs_general_noindex_filtered', 'afs_general_show_sort', 'afs_general_show_chips' ) as $key ) {
				$out[ $key ] = ( isset( $input[ $key ] ) && '0' !== (string) $input[ $key ] ) ? 1 : 0;
			}

			$layout                    = isset( $input['afs_general_layout'] ) ? sanitize_key( $input['afs_general_layout'] ) : 'list';
			$out['afs_general_layout'] = in_array( $layout, array( 'list', 'grid' ), true ) ? $layout : 'list';

			$design                       = isset( $input['afs_appearance_design'] ) ? sanitize_key( $input['afs_appearance_design'] ) : '';
			$out['afs_appearance_design'] = AFS_Design::is_valid( $design ) ? $design : AFS_Design::site_default();

			foreach ( array( 'afs_cards_show_image', 'afs_cards_show_excerpt', 'afs_cards_show_date', 'afs_cards_show_time', 'afs_cards_show_category' ) as $key ) {
				$out[ $key ] = ( isset( $input[ $key ] ) && '0' !== (string) $input[ $key ] ) ? 1 : 0;
			}
			$ratio                           = isset( $input['afs_cards_image_ratio'] ) ? AFS_Card::clean_ratio( $input['afs_cards_image_ratio'] ) : '';
			$out['afs_cards_image_ratio']    = '' === $ratio ? '16:10' : $ratio;
			$columns                         = isset( $input['afs_cards_columns'] ) ? absint( $input['afs_cards_columns'] ) : 3;
			$out['afs_cards_columns']        = min( 4, max( 1, $columns ? $columns : 3 ) );
			$words                           = isset( $input['afs_cards_excerpt_length'] ) ? absint( $input['afs_cards_excerpt_length'] ) : 25;
			$out['afs_cards_excerpt_length'] = min( 80, max( 10, $words ? $words : 25 ) );

			$color                        = isset( $input['afs_appearance_accent'] ) ? sanitize_hex_color( $input['afs_appearance_accent'] ) : '';
			$out['afs_appearance_accent'] = $color ? $color : AFS_Design::default_accent();

			return $out;
		}

		/** Public, viewable post types as name => object. */
		public static function viewable_post_types() {
			$types = get_post_types( array( 'public' => true ), 'objects' );
			unset( $types['attachment'] );
			return array_filter( $types, 'is_post_type_viewable' );
		}

		/** Taxonomies usable for tabs on a post type, as name => label. */
		public static function taxonomies_for( $post_type ) {
			$list = array();
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
				if ( 'post_format' === $tax->name ) {
					continue;
				}
				$list[ $tax->name ] = $tax->label;
			}
			return $list;
		}

		/**
		 * Output one settings field.
		 *
		 * @param array $args Field definition plus 'key'.
		 */
		public static function render_field( $args ) {
			$key   = $args['key'];
			$id    = 'afs-' . $key;
			$name  = AFS_SETTINGS . '[' . $key . ']';
			$value = self::afs_get_option( $key );

			switch ( $args['type'] ) {
				case 'post_type':
					echo '<select id="' . esc_attr( $id ) . '" class="general-post-type" name="' . esc_attr( $name ) . '">';
					foreach ( self::viewable_post_types() as $post_type ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $post_type->name ),
							selected( $value, $post_type->name, false ),
							esc_html( $post_type->labels->singular_name . ' (' . $post_type->name . ')' )
						);
					}
					echo '</select>';
					break;

				case 'taxonomy':
					$post_type = self::afs_get_option( 'afs_general_post_type' );
					$options   = self::taxonomies_for( $post_type );
					echo '<select id="' . esc_attr( $id ) . '" class="general-post-taxonomy" name="' . esc_attr( $name ) . '">';
					printf( '<option value="none"%s>%s</option>', selected( $value, 'none', false ), esc_html__( 'None', 'ajax-filter-search' ) );
					foreach ( $options as $tax_name => $tax_label ) {
						printf( '<option value="%s"%s>%s</option>', esc_attr( $tax_name ), selected( $value, $tax_name, false ), esc_html( $tax_label ) );
					}
					echo '</select>';
					break;

				case 'number':
					printf(
						'<input type="number" id="%s" name="%s" value="%d" min="1" max="100" step="1" class="small-text">',
						esc_attr( $id ),
						esc_attr( $name ),
						(int) $value
					);
					break;

				case 'ratio':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( array_keys( AFS_Card::RATIOS ) as $ratio ) {
						printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $ratio ), selected( $value, $ratio, false ) );
					}
					echo '</select>';
					break;

				case 'columns':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					for ( $c = 1; $c <= 4; $c++ ) {
						printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $c, selected( (int) $value, $c, false ) );
					}
					echo '</select>';
					break;

				case 'words':
					printf(
						'<input type="number" id="%s" name="%s" value="%d" min="10" max="80" step="1" class="small-text">',
						esc_attr( $id ),
						esc_attr( $name ),
						(int) $value
					);
					break;

				case 'yesno':
					echo '<fieldset id="' . esc_attr( $id ) . '" class="afs-seg"><legend class="screen-reader-text">' . esc_html( $args['label'] ) . '</legend>';
					printf( '<label class="afs-seg__opt"><input type="radio" name="%s" value="1"%s><span>%s</span></label>', esc_attr( $name ), checked( (int) $value, 1, false ), esc_html__( 'Yes', 'ajax-filter-search' ) );
					printf( '<label class="afs-seg__opt"><input type="radio" name="%s" value="0"%s><span>%s</span></label>', esc_attr( $name ), checked( (int) $value, 0, false ), esc_html__( 'No', 'ajax-filter-search' ) );
					echo '</fieldset>';
					break;

				case 'design':
					echo '<fieldset id="' . esc_attr( $id ) . '" class="afs-design-picker"><legend class="screen-reader-text">' . esc_html( $args['label'] ) . '</legend>';
					foreach ( AFS_Design::all() as $slug => $label ) {
						printf(
							'<label class="afs-design-option afs-design-option--%1$s"><input type="radio" name="%2$s" value="%1$s"%3$s><span class="afs-design-option__preview" aria-hidden="true">%5$s</span><span class="afs-design-option__name">%4$s</span></label>',
							esc_attr( $slug ),
							esc_attr( $name ),
							checked( $value, $slug, false ),
							esc_html( $label ),
							self::design_preview( $slug ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup built in design_preview().
						);
					}
					echo '</fieldset>';
					break;

				case 'layout':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					printf( '<option value="list"%s>%s</option>', selected( $value, 'list', false ), esc_html__( 'List', 'ajax-filter-search' ) );
					printf( '<option value="grid"%s>%s</option>', selected( $value, 'grid', false ), esc_html__( 'Grid', 'ajax-filter-search' ) );
					echo '</select>';
					break;

				case 'color':
					printf(
						'<input type="text" id="%s" name="%s" value="%s" class="plugin-colorpicker" data-default-color="%s">',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						esc_attr( AFS_Design::default_accent() )
					);
					break;
			}

			if ( ! empty( $args['help'] ) && empty( $args['no_help'] ) ) {
				echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
			}
		}

		/** Which fields belong to which card, in order. */
		private static function groups() {
			return array(
				'appearance' => array(
					'title'  => __( 'Appearance', 'ajax-filter-search' ),
					'intro'  => __( 'How feeds look on your site.', 'ajax-filter-search' ),
					'fields' => array( 'afs_appearance_design', 'afs_appearance_accent' ),
				),
				'cards'      => array(
					'title'   => __( 'Cards', 'ajax-filter-search' ),
					'intro'   => __( 'What each result shows in Editorial Grid and Resource Library.', 'ajax-filter-search' ),
					'callout' => array(
						__( 'Defaults, and how a feed overrides them.', 'ajax-filter-search' ),
						__( 'These apply to every feed that uses one of these designs. One feed can override any of them with a shortcode attribute (for example columns="2") or in the block\'s Cards panel ("Use plugin setting"). Classic and Modern ignore them.', 'ajax-filter-search' ),
					),
					'fold'    => true,
					'fields'  => array( 'afs_cards_show_image', 'afs_cards_image_ratio', 'afs_cards_columns', 'afs_cards_show_excerpt', 'afs_cards_excerpt_length', 'afs_cards_show_date', 'afs_cards_show_time', 'afs_cards_show_category' ),
				),
				'feed'       => array(
					'title'  => __( 'Feed', 'ajax-filter-search' ),
					'intro'  => __( 'What a feed lists by default. Every feed can override these with shortcode attributes or the block settings.', 'ajax-filter-search' ),
					'fields' => array( 'afs_general_post_type', 'afs_general_post_taxonomy', 'afs_general_posts_per_page', 'afs_general_layout' ),
				),
				'filters'    => array(
					'title'  => __( 'Filters and controls', 'ajax-filter-search' ),
					'intro'  => __( 'What visitors can use to narrow the results.', 'ajax-filter-search' ),
					'fields' => array( 'afs_general_show_filters', 'afs_general_views', 'afs_general_show_sort', 'afs_general_show_chips' ),
				),
				'search'     => array(
					'title'  => __( 'Search engines', 'ajax-filter-search' ),
					'intro'  => __( 'Keep filtered views out of search results so only your main page is indexed.', 'ajax-filter-search' ),
					'fields' => array( 'afs_general_noindex_filtered' ),
				),
			);
		}

		/** Small illustrations for the design picker (decorative). */
		private static function design_preview( $slug ) {
			if ( AFS_Design::EDITORIAL === $slug ) {
				return '<svg viewBox="0 0 160 104" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="160" height="104" fill="#f1f5f9"/><rect x="12" y="9" width="22" height="10" rx="5" fill="#2563eb"/><rect x="38" y="9" width="28" height="10" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="70" y="9" width="26" height="10" rx="5" fill="#fff" stroke="#e2e8f0"/><g><rect x="12" y="25" width="42" height="70" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="12" y="25" width="42" height="26" rx="5" fill="#f59e0b"/><rect x="17" y="56" width="14" height="4" rx="2" fill="#dbe5fb"/><rect x="17" y="64" width="30" height="4" rx="2" fill="#475569"/><rect x="17" y="72" width="26" height="3" rx="1.5" fill="#94a3b8"/><rect x="17" y="79" width="22" height="3" rx="1.5" fill="#94a3b8"/></g><g><rect x="59" y="25" width="42" height="70" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="59" y="25" width="42" height="26" rx="5" fill="#10b981"/><rect x="64" y="56" width="14" height="4" rx="2" fill="#dbe5fb"/><rect x="64" y="64" width="30" height="4" rx="2" fill="#475569"/><rect x="64" y="72" width="26" height="3" rx="1.5" fill="#94a3b8"/><rect x="64" y="79" width="22" height="3" rx="1.5" fill="#94a3b8"/></g><g><rect x="106" y="25" width="42" height="70" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="106" y="25" width="42" height="26" rx="5" fill="#6366f1"/><rect x="111" y="56" width="14" height="4" rx="2" fill="#dbe5fb"/><rect x="111" y="64" width="30" height="4" rx="2" fill="#475569"/><rect x="111" y="72" width="26" height="3" rx="1.5" fill="#94a3b8"/><rect x="111" y="79" width="22" height="3" rx="1.5" fill="#94a3b8"/></g></svg>';
			}
			if ( AFS_Design::LIBRARY === $slug ) {
				return '<svg viewBox="0 0 160 104" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="160" height="104" fill="#f1f5f9"/><rect x="12" y="9" width="92" height="14" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="108" y="9" width="40" height="14" rx="5" fill="#fff" stroke="#e2e8f0"/><rect x="12" y="29" width="136" height="68" rx="6" fill="#fff" stroke="#e2e8f0"/><rect x="18" y="35" width="14" height="14" rx="4" fill="#0ea5e9"/><rect x="38" y="36" width="56" height="4" rx="2" fill="#475569"/><rect x="38" y="44" width="76" height="3" rx="1.5" fill="#94a3b8"/><rect x="124" y="37" width="18" height="5" rx="2.5" fill="#dbe5fb"/><rect x="12" y="53" width="136" height="1" fill="#e2e8f0"/><rect x="18" y="58" width="14" height="14" rx="4" fill="#10b981"/><rect x="38" y="59" width="64" height="4" rx="2" fill="#475569"/><rect x="38" y="67" width="70" height="3" rx="1.5" fill="#94a3b8"/><rect x="124" y="60" width="18" height="5" rx="2.5" fill="#dbe5fb"/><rect x="12" y="76" width="136" height="1" fill="#e2e8f0"/><rect x="18" y="82" width="60" height="4" rx="2" fill="#475569"/><rect x="18" y="89" width="80" height="3" rx="1.5" fill="#94a3b8"/><rect x="124" y="83" width="18" height="5" rx="2.5" fill="#dbe5fb"/></svg>';
			}
			if ( AFS_Design::MODERN === $slug ) {
				return '<svg viewBox="0 0 160 104" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="160" height="104" fill="#f1f5f9"/><rect x="12" y="10" width="26" height="12" rx="6" fill="#2563eb"/><rect x="42" y="10" width="34" height="12" rx="6" fill="#fff" stroke="#e2e8f0"/><rect x="80" y="10" width="30" height="12" rx="6" fill="#fff" stroke="#e2e8f0"/><rect x="12" y="28" width="136" height="20" rx="7" fill="#fff" stroke="#e2e8f0"/><rect x="18" y="35" width="44" height="6" rx="3" fill="#e2e8f0"/><rect x="12" y="54" width="136" height="20" rx="7" fill="#fff" stroke="#e2e8f0"/><rect x="18" y="58" width="22" height="5" rx="2.5" fill="#dbe5fb"/><rect x="18" y="66" width="70" height="4" rx="2" fill="#94a3b8"/><rect x="12" y="78" width="136" height="20" rx="7" fill="#fff" stroke="#e2e8f0"/><rect x="18" y="82" width="22" height="5" rx="2.5" fill="#dbe5fb"/><rect x="18" y="90" width="84" height="4" rx="2" fill="#94a3b8"/></svg>';
			}
			return '<svg viewBox="0 0 160 104" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect width="160" height="104" fill="#fff"/><rect x="12" y="12" width="16" height="5" rx="2" fill="#475569"/><rect x="12" y="20" width="16" height="2" fill="#f1b45c"/><rect x="38" y="12" width="22" height="5" rx="2" fill="#94a3b8"/><rect x="70" y="12" width="26" height="5" rx="2" fill="#94a3b8"/><rect x="12" y="23" width="136" height="1" fill="#e2e8f0"/><rect x="12" y="30" width="136" height="16" rx="3" fill="#f6f7f8"/><rect x="18" y="36" width="40" height="5" rx="2" fill="#e2e8f0"/><rect x="12" y="52" width="136" height="1" fill="#f1b45c"/><rect x="12" y="58" width="22" height="5" rx="2" fill="#475569"/><rect x="44" y="58" width="70" height="5" rx="2" fill="#475569"/><rect x="12" y="68" width="136" height="1" fill="#e2e8f0"/><rect x="12" y="74" width="22" height="5" rx="2" fill="#475569"/><rect x="44" y="74" width="84" height="5" rx="2" fill="#475569"/><rect x="12" y="84" width="136" height="1" fill="#e2e8f0"/><rect x="12" y="90" width="22" height="5" rx="2" fill="#475569"/><rect x="44" y="90" width="60" height="5" rx="2" fill="#475569"/></svg>';
		}

		public static function options_page() {
			if ( ! current_user_can( AFS_CAPABILITIES ) ) {
				return;
			}
			$fields = self::fields();
			$icon   = AFS_PLUGIN_URL . '/admin/images/icon.svg';
			?>
			<div class="wrap afs-admin">
				<header class="afs-admin__header">
					<img class="afs-admin__icon" src="<?php echo esc_url( $icon ); ?>" alt="" width="52" height="52">
					<div class="afs-admin__title">
						<h1 id="afs-admin-title" tabindex="-1"><?php echo esc_html( AFS_PAGE_TITLE ); ?></h1>
						<p><?php esc_html_e( 'Filterable, searchable posts with no page reload.', 'ajax-filter-search' ); ?></p>
					</div>
					<?php if ( ! AFS_Announcement::should_show() ) : ?>
						<a class="afs-admin__whatsnew" href="<?php echo esc_url( AFS_Announcement::reopen_url() ); ?>"><?php esc_html_e( 'What\'s new', 'ajax-filter-search' ); ?></a>
					<?php endif; ?>
					<span class="afs-admin__version"><?php echo esc_html( 'v' . AFS_VERSION ); ?></span>
				</header>

				<?php AFS_Announcement::render(); ?>

				<form id="afs-form" class="afs-admin__form" action="options.php" method="post">
					<?php settings_fields( 'afs_settings_group' ); ?>
					<div class="afs-admin__shell">
						<nav class="afs-admin__nav" aria-label="<?php esc_attr_e( 'Settings sections', 'ajax-filter-search' ); ?>">
							<ul>
								<li><a href="#afs-sec-start"><?php esc_html_e( 'Add to a page', 'ajax-filter-search' ); ?></a></li>
								<?php foreach ( self::groups() as $group => $info ) : ?>
									<li><a href="#afs-sec-<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $info['title'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</nav>

						<div class="afs-admin__main">
							<section class="afs-card" id="afs-sec-start" aria-labelledby="afs-h-start">
								<h2 id="afs-h-start"><?php esc_html_e( 'Add to a page', 'ajax-filter-search' ); ?></h2>
								<p class="afs-card__intro"><?php esc_html_e( 'Paste a shortcode into any page, or add the Ajax Filter Search block in the editor.', 'ajax-filter-search' ); ?></p>
								<?php
								$copy = static function ( $example ) {
									printf(
										'<div class="afs-copy"><code>%1$s</code><button type="button" class="afs-copy__btn" data-afs-copy="%1$s">%2$s</button></div>',
										esc_attr( $example ),
										esc_html__( 'Copy', 'ajax-filter-search' )
									);
								};
								$copy( '[ajax_filter_search]' );
			?>
								<details class="afs-more">
									<summary><?php esc_html_e( 'More examples', 'ajax-filter-search' ); ?></summary>
									<?php
									foreach ( array(
										'[ajax_filter_search terms="news,events" posts_per_page="12" layout="grid"]',
										'[ajax_filter_search show_sort="true" show_chips="true" design="classic"]',
										'[ajax_filter_search design="editorial" columns="3" image_ratio="16:10" excerpt_length="25"]',
										'[ajax_filter_search design="library" show_sort="true" show_chips="true"]',
									) as $example ) {
										$copy( $example );
									}
									?>
								</details>
							</section>

							<?php foreach ( self::groups() as $group => $info ) : ?>
								<section class="afs-card" id="afs-sec-<?php echo esc_attr( $group ); ?>" aria-labelledby="afs-h-<?php echo esc_attr( $group ); ?>">
									<h2 id="afs-h-<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $info['title'] ); ?></h2>
									<p class="afs-card__intro"><?php echo esc_html( $info['intro'] ); ?></p>
									<?php if ( ! empty( $info['callout'] ) ) : ?>
										<p class="afs-callout"><strong><?php echo esc_html( $info['callout'][0] ); ?></strong> <?php echo esc_html( $info['callout'][1] ); ?></p>
									<?php endif; ?>
									<?php
									$fold = ! empty( $info['fold'] );
									if ( $fold ) {
										// Open when the site's own design uses these settings; otherwise folded, because only feeds that choose
										// one of the two designs use them (the options are still there, and still saved).
										$fold_open = AFS_Design::has_own_markup( AFS_Design::resolve( '' ) );
										echo '<details class="afs-fold"' . ( $fold_open ? ' open' : '' ) . '><summary>' . esc_html__( 'Card settings', 'ajax-filter-search' );
										if ( ! $fold_open ) {
											echo ' <small>' . esc_html__( 'Used by feeds that choose Editorial Grid or Resource Library.', 'ajax-filter-search' ) . '</small>';
										}
										echo '</summary>';
									}
									foreach ( $info['fields'] as $key ) {
										if ( ! isset( $fields[ $key ] ) ) {
											continue;
										}
										$field = $fields[ $key ];
										// A control that depends on another (for example the image shape on the featured image) is hidden, never
										// disabled or removed, while its parent is off, so its saved value is kept and submitted.
										$needs       = ! empty( $field['needs'] ) ? $field['needs'] : '';
										$needs_attrs = $needs ? ' data-afs-needs="' . esc_attr( AFS_SETTINGS . '[' . $needs . ']' ) . '"' . ( (int) self::afs_get_option( $needs ) ? '' : ' hidden' ) : '';
										?>
										<div class="afs-row"<?php echo $needs_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
											<div class="afs-row__label">
												<?php if ( in_array( $field['type'], array( 'yesno', 'design' ), true ) ) : ?>
													<?php // A fieldset is named by its legend; a label's "for" cannot point at it. ?>
													<span class="afs-row__title"><?php echo esc_html( $field['label'] ); ?></span>
												<?php else : ?>
													<label for="afs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
												<?php endif; ?>
												<?php if ( ! empty( $field['help'] ) ) : ?>
													<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
												<?php endif; ?>
											</div>
											<div class="afs-row__control">
												<?php
												self::render_field(
													array_merge(
														$field,
														array(
															'key'     => $key,
															'no_help' => true,
														)
													)
												);
												?>
											</div>
										</div>
										<?php
									}
									if ( $fold ) {
										echo '<p class="afs-kept" hidden>' . esc_html__( 'Hidden options keep their saved values and apply again if you turn the option back on.', 'ajax-filter-search' ) . '</p></details>';
									}
									?>
								</section>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="afs-savebar">
						<?php submit_button( __( 'Save Settings', 'ajax-filter-search' ), 'primary', 'submit', false ); ?>
						<span class="afs-savebar__note" role="status" aria-live="polite"></span>
					</div>
				</form>
			</div>
			<?php
		}

		/** Admin assets, only on this plugin's settings screen. */
		public static function load_admin_scripts( $hook_suffix ) {
			if ( 'settings_page_' . AFS_MENU_SLUG !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( AFS_SUB . '-admin-style', AFS_PLUGIN_URL . '/admin/css/settings.css', array( 'wp-color-picker' ), AFS_VERSION );
			wp_enqueue_script(
				AFS_SUB . '-admin-script-js',
				AFS_PLUGIN_URL . '/admin/js/admin.js',
				array( 'jquery', 'wp-color-picker' ),
				AFS_VERSION,
				true
			);

			$map = array();
			foreach ( array_keys( self::viewable_post_types() ) as $post_type ) {
				$map[ $post_type ] = self::taxonomies_for( $post_type );
			}
			wp_localize_script(
				AFS_SUB . '-admin-script-js',
				'afsAdmin',
				array(
					'taxonomies' => $map,
					'none'       => __( 'None', 'ajax-filter-search' ),
					'copied'     => __( 'Copied', 'ajax-filter-search' ),
					'unsaved'    => __( 'You have unsaved changes.', 'ajax-filter-search' ),
					'dismissed'  => __( 'Announcement dismissed.', 'ajax-filter-search' ),
				)
			);
		}
	}

endif;
