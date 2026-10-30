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
		const DEFAULT_ACCENT     = '#f1b45c';

		/**
		 * Field definitions. Keys are the stored option keys (unchanged from 1.x).
		 *
		 * @return array<string,array>
		 */
		public static function fields() {
			return array(
				'afs_general_post_type'      => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'post_type',
					'label'   => __( 'Post type', 'ajax-filter-search' ),
					'help'    => __( 'Select a post type to pull the feed from.', 'ajax-filter-search' ),
					'default' => 'post',
				),
				'afs_general_post_taxonomy'  => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'taxonomy',
					'label'   => __( 'Taxonomy', 'ajax-filter-search' ),
					'help'    => __( 'Select a taxonomy for the tabbed filters.', 'ajax-filter-search' ),
					'default' => 'category',
				),
				'afs_general_posts_per_page' => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'number',
					'label'   => __( 'Posts per page', 'ajax-filter-search' ),
					'help'    => __( 'How many posts to display per page (1 to 100).', 'ajax-filter-search' ),
					'default' => 10,
				),
				'afs_general_show_filters'   => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Category tabs', 'ajax-filter-search' ),
					'help'    => __( 'Display tabbed filters.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_general_views'          => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'yesno',
					'label'   => __( 'Layout switcher', 'ajax-filter-search' ),
					'help'    => __( 'Show the List View / Grid View switcher.', 'ajax-filter-search' ),
					'default' => 1,
				),
				'afs_general_layout'         => array(
					'section' => self::SECTION_GENERAL,
					'type'    => 'layout',
					'label'   => __( 'Default layout', 'ajax-filter-search' ),
					'help'    => __( 'The layout used when a page first loads.', 'ajax-filter-search' ),
					'default' => 'list',
				),
				'afs_appearance_accent'      => array(
					'section' => self::SECTION_APPEARANCE,
					'type'    => 'color',
					'label'   => __( 'Accent color', 'ajax-filter-search' ),
					'help'    => __( 'Used for links, the active tab and the active page.', 'ajax-filter-search' ),
					'default' => self::DEFAULT_ACCENT,
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

			foreach ( array( 'afs_general_show_filters', 'afs_general_views' ) as $key ) {
				$out[ $key ] = ( isset( $input[ $key ] ) && '0' !== (string) $input[ $key ] ) ? 1 : 0;
			}

			$layout                    = isset( $input['afs_general_layout'] ) ? sanitize_key( $input['afs_general_layout'] ) : 'list';
			$out['afs_general_layout'] = in_array( $layout, array( 'list', 'grid' ), true ) ? $layout : 'list';

			$color                        = isset( $input['afs_appearance_accent'] ) ? sanitize_hex_color( $input['afs_appearance_accent'] ) : '';
			$out['afs_appearance_accent'] = $color ? $color : self::DEFAULT_ACCENT;

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

				case 'yesno':
					echo '<fieldset id="' . esc_attr( $id ) . '"><legend class="screen-reader-text">' . esc_html( $args['label'] ) . '</legend>';
					printf( '<label><input type="radio" name="%s" value="1"%s> %s</label> &nbsp; ', esc_attr( $name ), checked( (int) $value, 1, false ), esc_html__( 'Yes', 'ajax-filter-search' ) );
					printf( '<label><input type="radio" name="%s" value="0"%s> %s</label>', esc_attr( $name ), checked( (int) $value, 0, false ), esc_html__( 'No', 'ajax-filter-search' ) );
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
						esc_attr( self::DEFAULT_ACCENT )
					);
					break;
			}

			if ( ! empty( $args['help'] ) ) {
				echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
			}
		}

		public static function options_page() {
			if ( ! current_user_can( AFS_CAPABILITIES ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php echo esc_html( AFS_PAGE_TITLE ); ?></h1>
				<form id="afs-form" action="options.php" method="post">
					<?php
					settings_fields( 'afs_settings_group' );
					do_settings_sections( 'afs_settings_page' );
					submit_button( __( 'Save Settings', 'ajax-filter-search' ) );
					?>
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
				)
			);
		}
	}

endif;
