<?php 
/*******************************************************************
* BUILD ADMIN SETTINGS

Reference: http://wpsettingsapi.jeroensormani.com/
********************************************************************/
if( !class_exists('AFSAdmin') ) :

	class AFSAdmin {
			
		
		/* TABS */
		static $tabs = array(
		
			// General Tab Info:
			array(
				'name' 			=> 'general',
				'title' 			=> 'Below are the general options for the Filter Display:',
				'fields'			=> array(
										array(
											'field_name' 		=> '_post_type',
											'field_description' => 'Select a post type to pull the feed from. (default is "post")'
										),
										array(
											'field_name' 		=> '_post_taxonomy',
											'field_description' => 'Select a post taxonomy for the tabbed filters. (default is "post")'
										),
										array(
											'field_name' 		=> '_posts_per_page',
											'field_description' => 'How many posts to display per page.'
										),
										array(
											'field_name' 		=> '_show_filters',
											'field_description' => 'Display tabbed filters.'
										),
										array(
											'field_name' 		=> '_views',
											'field_description' => 'Show optional views. (List View / Grid View)'
										),
										/*array(
											'field_name' 		=> '_repeatable_meta_box_display',
											'field_description' => 'test'
										),*/
								   )
			),
			/*array(
				'name' 			=> 'style_options',
				'title' 			=> 'Style the Filter Display according to your brand:',
				'fields'			=> array(
										array(
											'field_name' 		=> '_table_header',
											'field_description' => 'Select a color for the table header.'
										),
								   )
			),
			array(
				'name' 			=> 'directions',
				'title' 			=> '',
				'fields'			=> array(
										array(
											'field_name' 		=> '_content',
											'field_description' => ''
										),
								   )
			),*/
		);
		
		/* CALLBACK DESCRIPTION FUNCTIONS FOR EACH TAB SECTION */
		static function afs_settings_general_section_callback(  ) { 
			echo __( 'Customize your settings below and then copy and paste the shortcode <strong>[ajax_filter_search]</strong> on the page you\'d like the feed to display.', 'wordpress' );
		}
		static function afs_settings_style_options_section_callback(  ) { 
			echo __( '', 'wordpress' );
		}
		//function afs_settings_directions_section_callback(  ) { 
			//echo __( '', 'wordpress' );
		//}
		
		/* RETRIEVE VALUES */
		static function afs_retrieve($val) {
			$options 	= get_option( AFS_SETTINGS );
			$value		= $options[AFS_SUB.$val];
			return $value;
		}
		
		/******************************************
		* INITIALIZE EVERYTHING
		******************************************/
		static function init() {
			// Admin Settings, Styles & Scripts
			add_action( 'admin_menu', array(__CLASS__, AFS_SUB.'_add_admin_menu') );
			add_action( 'admin_init', array(__CLASS__, AFS_SUB.'_settings_init') );
			add_action( 'admin_enqueue_scripts', array(__CLASS__, 'load_admin_scripts'), 100);
			
			// Adds Settings Link to Plugins Page
			add_filter( 'plugin_action_links_'.AFS_PLUGIN_FILE, array(__CLASS__, 'plugin_settings_link') );
			
			// Core Styles & Scripts
			add_action( 'wp_enqueue_scripts', array(__CLASS__, 'load_core_scripts'), 999 );
			
			
		}
		
		
		/******************************************
		* BUILD ADMIN AREA
		******************************************/
		// Set Up Admin Area
		static function afs_add_admin_menu(  ) { 	
			add_options_page( AFS_PAGE_TITLE, AFS_MENU_TAB_TITLE, AFS_CAPABILITIES, AFS_MENU_SLUG, array(__CLASS__, AFS_CALLBACK) );
		}
		
		// Add Settings Link to Plugins Page
		static function plugin_settings_link($links) {
			$url = get_admin_url() . 'options-general.php?page='.AFS_MENU_SLUG;
			$settings_link = '<a href="'.$url.'">' . __( 'Settings', AFS_MENU_SLUG ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}
		
		// Initialize The Tabs
		static function init_setting_tabs (  ) {  
			
			$tab_array = self::$tabs;
			foreach($tab_array as $tab) {
					
				// Register Tab & Create Settings Section
				register_setting( AFS_SUB.'_'.$tab['name'], AFS_SETTINGS );
				
				$tab_name 		= AFS_SUB.'_'.$tab['name'];
				$tab_section	= $tab_name.'_section';
				add_settings_section(
					$tab_section, 
					__( $tab['title'], 'wordpress' ), 
					array(__CLASS__, AFS_SETTINGS.'_'.$tab['name'].'_section_callback'), 
					$tab_name
				);
				
				// Set Up Form Fields Defined In $tabs array
				$fields =  $tab['fields'];
				if($fields) {
					foreach($fields as $field) {
						
						$field_function = AFS_SUB.'_'.$tab['name'].$field['field_name'];
						
						add_settings_field( 
							$field_function, 
							__( $field['field_description'], 'wordpress' ), 
							array(__CLASS__, $field_function), 
							$tab_name, 
							$tab_section 
						);
					}
				}
				
			}
			
		}	
		
		// Initialize the tabs and all it's data
		static function afs_settings_init(  ) { 	
			
			// Grab Tab Information
			self::init_setting_tabs();		
		
		} 
		
		/* REGISTER FORM FIELD FUNCTIONS  */
		
		// General
		static function afs_general_post_type(  ) { 
	
			$options = get_option( AFS_SETTINGS );
			
			// Select a Post Type
			$args = array(
			   'public'   => true,
			   //'_builtin' => false
			);
			
			$output = 'objects'; // names or objects, note names is the default
			$operator = 'and'; // 'and' or 'or'
			$post_types = get_post_types( $args, $output, $operator ); 
			
			?>
			<?php 
				if($options[__FUNCTION__] == '') { 
					$options[__FUNCTION__] = 'post'; 
				} 
			?>
			<select class="general-post-type" name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>'>
				
				<!--<option value='0' <?php selected( $options[__FUNCTION__], 0 ); ?>>--</option>-->
				
				<?php foreach ( $post_types  as $post_type ) { ?>
					
					<option value='<?php echo $post_type->name ?>' <?php selected( $options[__FUNCTION__], $post_type->name ); ?>><?php echo $post_type->name; ?></option>
				   
				<?php } ?>
			</select>
		
		<?php
		}
		
		static function afs_general_post_taxonomy(  ) { $options = get_option( AFS_SETTINGS ); ?>
				
			<select class="general-post-taxonomy" name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>'>
				<?php 
					$cur_post_tax		= $options[__FUNCTION__];
					$post_type 			= AFSAdmin::afs_retrieve('_general_post_type');
					if($post_type == '') { $post_type = 'post'; }
					$taxonomy_objects 	= get_object_taxonomies($post_type, 'objects');
		
					if($taxonomy_objects) {
						foreach($taxonomy_objects as $tax) {
							$sel = '';
							if($tax->name == 'post_format') {
								continue;
							} else {
								if($tax->name == $cur_post_tax) { $sel = 'selected="selected"'; }
								echo '<option value="'.$tax->name.'" '.$sel.'>'.$tax->label.'</option>';
							}
						}	
					} else {
						echo '<option value="none" '.$sel.'>None</option>';
					}
				?>
			</select>
		
		<?php
		
		}
		
		static function afs_general_posts_per_page(  ) {  $options = get_option( AFS_SETTINGS ); 
			
			// Set Default:
			if($options[__FUNCTION__] == '') { $options[__FUNCTION__] = get_option( 'posts_per_page' ); }
			//get_option( AFS_SETTINGS.'['.__FUNCTION__.']', get_option( 'posts_per_page' ) );
			
			?>
		
			<input type='text' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' value='<?php echo $options[__FUNCTION__]; ?>'>
		<?php
		}
	
		static function afs_general_show_filters(  ) { $options = get_option( AFS_SETTINGS ); 
			
			if($options[__FUNCTION__] == '') { $options[__FUNCTION__] = 1; }
			
			?>
			
			Yes
			<input type='radio' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' <?php checked( $options[__FUNCTION__], 1 ); ?> value='1'>
			&nbsp; No
			<input type='radio' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' <?php checked( $options[__FUNCTION__], 0 ); ?> value='0'>
		<?php
		}
		
		static function afs_general_views(  ) { $options = get_option( AFS_SETTINGS ); 
			
			if($options[__FUNCTION__] == '') { $options[__FUNCTION__] = 1; }
			
			?>
			
			Yes
			<input type='radio' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' <?php checked( $options[__FUNCTION__], 1 ); ?> value='1'>
			&nbsp; No
			<input type='radio' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' <?php checked( $options[__FUNCTION__], 0 ); ?> value='0'>
		<?php
		}
		
		
		// Style Options
		static function afs_style_options_table_header(  ) { $options = get_option( AFS_SETTINGS );  ?>
			
			<input name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' type="text" class="plugin-colorpicker" value="<?php echo $options[__FUNCTION__]; ?>">
            			
		<?php
		} 
		
		
		// Directions
		static function afs_directions_content(  ) { ?>
			
			<style type="text/css">p.submit {display:none;}</style>
		
		<?php	
		} 
		
		
		
		
		static function afs_get_sample_options() {
			$theoptions = array (
				'Option 1' => 'option1',
				'Option 2' => 'option2',
				'Option 3' => 'option3',
				'Option 4' => 'option4',
			);
			
			return $theoptions;
		}
		
		static function afs_add_meta_boxes() {
			add_meta_box( 'repeatable-fields', 'Repeatable Fields', AFS_SUB.'_general_repeatable_meta_box_display', 'post', 'normal', 'default');
		}
		static function afs_general_repeatable_meta_box_display() { $options = get_option( AFS_SETTINGS );
			
			$repeatable_fields = $options[__FUNCTION__];
			$theoptions = self::afs_get_sample_options();
			wp_nonce_field( AFS_SUB.'_repeatable_meta_box_nonce', AFS_SUB.'_repeatable_meta_box_nonce' );
			?>
			<script type="text/javascript">
			jQuery(document).ready(function( $ ){
				$( '#add-row' ).on('click', function() {
					var row = $( '.empty-row.screen-reader-text' ).clone(true);
					row.removeClass( 'empty-row screen-reader-text' );
					row.insertBefore( '#repeatable-fieldset-one tbody>tr:last' );
					return false;
				});
			
				$( '.remove-row' ).on('click', function() {
					$(this).parents('tr').remove();
					return false;
				});
			});
			</script>
		  
			<table id="repeatable-fieldset-one" width="100%">
			<thead>
				<tr>
					<th width="40%">Name</th>
					<th width="12%">Select</th>
					<th width="40%">URL</th>
					<th width="8%"></th>
				</tr>
			</thead>
			<tbody>
            
            
			<?php print_r($repeatable_fields);
			
			if ( $repeatable_fields ) :
			
			foreach ( $repeatable_fields as $field ) {
			?>
			<tr>
				<td><input type="text" class="widefat" name="<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>[name]" value="<?php if($field['name'] != '') { echo esc_attr( $field['name'] ); } ?>" /></td>
			
				<td>
					<?php /*<select name="select[]">
					<?php foreach ( $theoptions as $label => $value ) : ?>
					<option value="<?php echo $value; ?>"<?php selected( $field['select'], $value ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
					</select>*/ ?>
				</td>
			
				<td><?php /*<input type="text" class="widefat" name="url[]" value="<?php if ($field['url'] != '') echo esc_attr( $field['url'] ); else echo 'http://'; ?>" />*/ ?></td>
			
				<td><a class="button remove-row" href="#">Remove</a></td>
			</tr>
			<?php
			}
			else :
			// show a blank one
			?>
			<tr>
				<td><input type="text" class="widefat" name="<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>[name]" /></td>
			
				<td>
					<?php /*<select name="select[]">
					<?php foreach ( $theoptions as $label => $value ) : ?>
					<option value="<?php echo $value; ?>"><?php echo $label; ?></option>
					<?php endforeach; ?>
					</select>*/ ?>
				</td>
			
				<td><?php /*<input type="text" class="widefat" name="url[]" value="http://" />*/ ?></td>
			
				<td><a class="button remove-row" href="#">Remove</a></td>
			</tr>
			<?php endif; ?>
			
			<!-- empty hidden one for jQuery -->
			<tr class="empty-row screen-reader-text">
				<td><input type="text" class="widefat" name="<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>[name]" /></td>
			
				<td>
					<?php /*<select name="select[]">
					<?php foreach ( $theoptions as $label => $value ) : ?>
					<option value="<?php echo $value; ?>"><?php echo $label; ?></option>
					<?php endforeach; ?>
					</select>*/ ?>
				</td>
				
				<td><?php /*<input type="text" class="widefat" name="url[]" value="http://" />*/ ?></td>
				  
				<td><a class="button remove-row" href="#">Remove</a></td>
			</tr>
			</tbody>
			</table>
			
			<p><a id="add-row" class="button" href="#">Add another</a></p>
			<?php
		}
		
		static function afs_repeatable_meta_box_save($post_id) {
			if ( ! isset( $_POST[AFS_SUB.'_repeatable_meta_box_nonce'] ) ||
			! wp_verify_nonce( $_POST[AFS_SUB.'_repeatable_meta_box_nonce'], AFS_SUB.'_repeatable_meta_box_nonce' ) )
				return;
			
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
				return;
			
			if (!current_user_can('edit_post', $post_id))
				return;
			
			$old = get_post_meta($post_id, 'repeatable_fields', true);
			$new = array();
			$options =  self::afs_get_sample_options();
			
			$names = $_POST['name'];
			$selects = $_POST['select'];
			$urls = $_POST['url'];
			
			$count = count( $names );
			
			for ( $i = 0; $i < $count; $i++ ) {
				if ( $names[$i] != '' ) :
					$new[$i]['name'] = stripslashes( strip_tags( $names[$i] ) );
					
					if ( in_array( $selects[$i], $options ) )
						$new[$i]['select'] = $selects[$i];
					else
						$new[$i]['select'] = '';
				
					if ( $urls[$i] == 'http://' )
						$new[$i]['url'] = '';
					else
						$new[$i]['url'] = stripslashes( $urls[$i] ); // and however you want to sanitize
				endif;
			}
			if ( !empty( $new ) && $new != $old )
				update_post_meta( $post_id, 'repeatable_fields', $new );
			elseif ( empty($new) && $old )
				delete_post_meta( $post_id, 'repeatable_fields', $old );
		}
		
		
		
		/* Other example Form Fields:
		
		function afs_checkbox_example(  ) { $options = get_option( AFS_SETTINGS ); ?>
			<input type='checkbox' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>' <?php checked( $options[__FUNCTION__], 1 ); ?> value='1'>
		<?php
		}
		
		function afs_textarea_example(  ) { $options = get_option( AFS_SETTINGS ); ?>
			<textarea cols='40' rows='5' name='<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>'> 
				<?php echo $options['__FUNCTION__']; ?>
			</textarea>
		<?php
		}	
		
		// Image Uploader
		function afs_general_upload_media( ) { $options = get_option( AFS_SETTINGS ); ?>
			<div>
				<!--<label for="image_url">Image</label>-->
				<input type="hidden" name="<?php echo AFS_SETTINGS.'['.__FUNCTION__.']';?>" id="image_url" class="regular-text" value="<?php echo $options[__FUNCTION__]; ?>">
				<input type="button" name="upload-btn" id="upload-btn" class="button-secondary" value="Upload Image">
			
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				function show_media(){
					$('a#upload-btn, input#upload-btn').click(function(e) {
						e.preventDefault();
						var image = wp.media({ 
							title: 'Upload Image',
							// mutiple: true if you want to upload multiple files at once
							multiple: false
						}).open()
						.on('select', function(e){
							// This will return the selected image from the Media Uploader, the result is an object
							var attachment = image.state().get('selection').first();
							// We convert uploaded_image to a JSON object to make accessing it easier
							// Output to the console uploaded_image
							console.log(attachment);
							var image_url 	= attachment.toJSON().url;
							var image_thumb = attachment.toJSON().sizes.thumbnail.url;
							// Let's assign the url value to the input field
							$('#image_url').val(image_url);
							$('a#upload-btn').remove();
							$('input#upload-btn').val('Edit Image');
							$('input#upload-btn').parent('div').prepend('<a id="upload-btn" href="#" style="margin-right:10px;"><img src="'+image_thumb+'"></a>');
							show_media();
							
						});
					});
				}
				show_media();
			});
			</script>
		<?php
		} */
		
		
		
		
		// Display Form HTML
		static function afs_options_page(  ) {  ?>
		
			<div class="wrap">
				<form id="afs-form" action='options.php' method='post'>
					
					<div id="icon-themes" class="icon32"></div>
					<h1><?php echo AFS_PAGE_TITLE; ?></h1>
					<?php //settings_errors(); ?>
					<br>
					
					<?php
						$all_tabs 		= self::$tabs;
						$default_tab 	= $all_tabs[0]['name'];
						$active_tab 	= isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : $default_tab;
					?>
					
					<h2 class="nav-tab-wrapper">
						<?php 
							foreach($all_tabs as $tab) { ?>
								<a href="?page=<?php echo AFS_MENU_SLUG; ?>&tab=<?php echo $tab['name']; ?>" class="nav-tab <?php echo $active_tab == $tab['name'] ? 'nav-tab-active' : ''; ?>"><?php echo ucwords(str_replace('_',' ',$tab['name'])); ?></a>
							<?php }
						?>
					</h2>
			
					<?php
					if( $active_tab == $default_tab ) {
						settings_fields( AFS_SUB.'_'.$default_tab );
						do_settings_sections( AFS_SUB.'_'.$default_tab );
					} else {
						settings_fields( AFS_SUB.'_'.$active_tab );
						do_settings_sections( AFS_SUB.'_'.$active_tab );
					}
					
					submit_button();
					?>
			
				</form>
			</div>
			<?php
		}
		
		
		/******************************************
		* REGISTER STYLES & SCRIPTS
		******************************************/
		static function load_core_scripts() {
		
			global $wp_styles;
			
			if (!is_admin()) {
		
				// Register Files
				wp_register_style( AFS_SUB.'-fontawesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css', array(), '', 'all' );	
				wp_register_style( AFS_SUB.'-main', AFS_PLUGIN_URL.'/core/css/main.min.css', array(), '', 'all' );	
				wp_register_style( AFS_SUB.'-custom', AFS_PLUGIN_URL.'/core/css/custom.min.css', array(), '', 'all' );	
		
				wp_register_script( AFS_SUB.'-script-js', AFS_PLUGIN_URL.'/core/js/script.min.js', array( 'jquery' ), '', true );
		
				//if ( is_front_page() && is_home() ) {
				  // Default homepage
				//} elseif ( is_front_page() ) {
				  // static homepage
				//} elseif ( is_home() || is_singular('post')) {
					// blog page
				  
					// Enqueue All the Styles and Scripts
					wp_enqueue_script( AFS_SUB.'-script-js' );
		
					wp_enqueue_style( AFS_SUB.'-fontawesome' );
					wp_enqueue_style( AFS_SUB.'-main' );
					wp_enqueue_style( AFS_SUB.'-custom' );
		
				//} else {
				  //everything else
				//}
			}
		}
	
		
		// Load Admin Scripts
		static function load_admin_scripts() {
			
			// Color Picker Scripts:
			wp_enqueue_style('wp-color-picker');
			wp_enqueue_script('iris', admin_url('js/iris.min.js'),array('jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch'), false, 1);
			wp_enqueue_script('wp-color-picker', admin_url('js/color-picker.min.js'), array('iris'), false,1);
			$colorpicker_l10n = array('clear' => __('Clear'), 'defaultString' => __('Default'), 'pick' => __('Select Color'));
			wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', $colorpicker_l10n ); 
			
			// Media Uploader Scripts:
			wp_enqueue_media();
			
			// Plugin Scripts
			wp_register_script( AFS_SUB.'-admin-script-js', AFS_PLUGIN_URL.'/admin/js/admin.min.js', array( 'jquery' ), '', true );
			wp_enqueue_script( AFS_SUB.'-admin-script-js' );
		}
		
	}

endif;

// TURN ON THE SETTINGS
$afs_admin = new AFSAdmin();
$afs_admin::init();

?>